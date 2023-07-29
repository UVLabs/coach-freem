<?php

/**
 * Coach Freem adds contacts to Mautic when they install a freemius plugin. 
 *
 * His tasks should be kept limited(hopefully). Currently his only task is creating a contact.
 *
 * That might change in the future.
 * 
 * Author:          Uriahs Victor
 * Created on:      29/03/2023 (d/m/y)
 *
 * @link    https://uriahsvictor.com
 * @since   1.0.1
 * @license GPLv2
 * @version 1.2.0
 */

use CoachFreem\Contacts\Create as CreateContact;
use CoachFreem\Contacts\Update as UpdateContact;
use CoachFreem\Logger;

define('ABSPATH', dirname(__FILE__));
require 'vendor/autoload.php';
init();

/**
 * Start Coach Freem.
 * 
 * @return void 
 * @since 1.2.0
 */
function init()
{

    $save = $_GET['save'] ?? false;
    $process = $_GET['process'] ?? false;

    if ($save) {
        save_webhook();
    }

    if ($process) {
        check_webhooks();
    }
}

/**
 * Save a webhook to the file system.
 * 
 * @return void 
 * @since 1.2.0
 */
function save_webhook(): void
{

    $time_start = microtime(true);

    $body_json = file_get_contents("php://input");

    $body_array = json_decode($body_json, true);

    $webhook_id = $body_array['id'] ?? time();

    $name = "webhook_$webhook_id";
    $saved = file_put_contents("./webhooks/$name.json", $body_json . PHP_EOL, LOCK_EX);

    if ($saved) {
        echo 'success';
    } else {
        Logger::log("There was an issue saving the webhook to file: #$webhook_id");
        echo 'failed';
        exit();
    }

    $time_end = microtime(true);
    $duration = $time_end - $time_start;

    Logger::log("Webhook #$webhook_id save duration: $duration");
}

/**
 * Check saved webhooks and process one.
 * 
 * @return void 
 * @since 1.2.0
 */
function check_webhooks(): void
{

    $webhook_files = array_diff(scandir('./webhooks/'), array('.', '..', '.gitkeep'));

    if (empty($webhook_files)) {
        exit('no pending webhooks found to process');
    }

    /**
     * Grab webhooks in order so that install.installed action can happen before others.
     * If we don't do this then there's a chance the user can get wrongly tagged. 
     * Example in case where a deactivated tag event happens after an uninstalled event. 
     * The user will have the deactivated tag and uninstalled tag when really they should just have the uninstalled tag.
     */
    asort($webhook_files);
    $filename = current($webhook_files);
    $webhook_file_path = "./webhooks/$filename";

    $body = file_get_contents($webhook_file_path);

    if (empty($body)) {
        Logger::log("issue reading saved webhook file: $filename");
        exit('issue reading saved webhook');
    }

    $response = process_webhook($body);

    if ($response === 0) {
        Logger::log("\$id returned 0 for processing webhook file: $filename");
        exit("Something went wrong processing webhook. Check Coach's logs.");
    }

    echo $response;
    unlink($webhook_file_path); // Delete webhook file after processing.
}

/**
 * Process a webhook.
 * 
 * @since 1.0.0
 * @since 1.2.0 renamed function.
 */
function process_webhook($body)
{
    $time_start = microtime(true);

    $body = json_decode($body, true);
    $webhook_id = $body['id'] ?? '';

    $user_data = $body['objects']['user'] ?? '';

    if (empty($user_data)) {
        Logger::log("No user data recieved in the webhook #$webhook_id");
        exit('no user data');
    }

    /**
     * Only opted in contacts please...
     */
    if (empty($user_data['is_marketing_allowed'])) {
        return ('user didn\'t opt into marketing');
    }

    $install = $body['objects']['install'] ?? array();
    $user_data['is_premium'] = $install['is_premium'] ?? false; // Using this to decide how to tag contacts.

    $plugin_id = $body['plugin_id'] ?? '';

    if (empty($plugin_id)) {
        Logger::log('Plugin ID value in webhook is empty.', $body);
        exit('plugin id empty');
    }

    $event_type = $body['type'] ?? '';

    /**
     * Bail if email is excluded.
     */
    $user_email = $user_data['email'] ?? '';
    if (in_array($user_email, excludedEmails()) || empty($user_email)) {
        Logger::log("This email address is excluded. User email: $user_email");
        return ('excluded email'); // Return a value so that the webhook file will be deleted.
    }

    /**
     * Bail if TLD is excluded.
     */
    $domain = $install['url'] ?? '';
    if (isExcludedTLD($domain)) {
        Logger::log("This is a development domain. Domain: $domain");
        return ('development domain'); // Return a value so that the webhook file will be deleted.
    }

    $contactCreate = new CreateContact($plugin_id);
    $contactUpdate = new UpdateContact($user_data);
    $product = array_flip(productIDs())[$plugin_id] ?? '';

    /**
     * Bail if the plugin ID received is not in the array provided in productIDs()
     */
    if (empty($product)) {
        Logger::log('This Product ID was not found in array of available IDs in productIDs() function.', $body);
        exit('product id not found in array');
    }

    $installed_tag = $product . '-installed';
    $uninstalled_tag = $product . '-uninstalled';
    $activated_tag = $product . '-activated';
    $deactivated_tag = $product . '-deactivated';

    switch ($event_type) {
        case 'install.installed': // Plugin Installed

            $custom_mappings = customContactDataMappings();
            $segments = contactSegments();
            $tags = contactTags();

            $contactCreate->setCustomMappings($custom_mappings)
                ->setSegments($segments)
                ->setTags($tags)
                ->add($user_data);

            $id = $contactUpdate->updateContactTags(array($installed_tag), array($uninstalled_tag));
            break;
        case 'license.activated': // Add pro tag and remove free tag

            $free_tags = contactTags()[$plugin_id]['free-users-tags'];
            $premium_tags = contactTags()[$plugin_id]['premium-users-tags'];

            $id = $contactUpdate->updateContactTags($premium_tags, $free_tags);
            break;
        case 'license.deactivated': // Add free tag and remove pro tag
        case 'license.expired': // Add free tag and remove pro tag

            $free_tags = contactTags()[$plugin_id]['free-users-tags'];
            $premium_tags = contactTags()[$plugin_id]['premium-users-tags'];

            $id = $contactUpdate->updateContactTags($free_tags, $premium_tags);
            break;
        case 'install.activated': // Plugin activated

            $id = $contactUpdate->updateContactTags(array($activated_tag), array($deactivated_tag));
            break;
        case 'install.deactivated': // Plugin deactivated

            $id = $contactUpdate->updateContactTags(array($deactivated_tag), array($activated_tag));
            break;
        case 'install.uninstalled': // Plugin uninstalled

            $id = $contactUpdate->updateContactTags(array($uninstalled_tag), array($installed_tag, $activated_tag, $deactivated_tag));
            break;
        default:

            $id = 0;
            break;
    }

    http_response_code(200);

    $time_end = microtime(true);
    $duration = $time_end - $time_start;
    Logger::log("Webhook #$webhook_id execution duration: $duration");

    /**
     * This will be 0/null if: 
     * 
     * The username and password set for the Client is wrong. 
     * The URL you set for the Mautic API is wrong.
     * The contact email is in the excluded list.
     * Trying to update a user ID that does not exist.
     */
    return $id;
}

// ------ 
// Coach Freem is opinionated. 
//
// All Data SHOULD be easy to set by those planning to use him, that means all customizable data should exist in a functions inside this file.
// ------ 

/**
 * Product IDs retrieved from Freemius.
 * 
 * Replace the product key and values below with your own products.
 * Be sure to make the relevant modifications in customContactDataMappings(), contactSegments() and contactTags() to reflect the changes.
 * 
 * @return array 
 * @since 1.1.2
 */
function productIDs(): array
{
    return array(
        'kikote' => 8507,
        'dps' => 11538,
        'printus' => 12321,
    );
}

/**
 * Custom mappings for Freemius data sent to Mautic API.
 * 
 * This is useful if you have a custom field inside Mautic that isn't the same name as the one being sent by Freemius. 
 * Use the Plugin ID to differenciate mappings if you have multiple Freemius plugins, and you are sending all of their webhooks Coach Freem. 
 * 
 * Note that for "gross" in this example, I am mapping it to "kikote_gross" which is a custom field I created in Mautic to track the Gross of that plugin for a contact.
 * You'd have to first create your custom field inside Mautic before being able to assign custom data to it.
 * 
 * @return array 
 * @since  1.0.0
 */
function customContactDataMappings(): array
{
    $product_ids = productIDs();

    $hold = array();

    /**
     * Edit the $hold array with your custom mappings.
     */
    foreach ($product_ids as $product_name => $product_id) {
        $hold[$product_id] = array(
            'id' => 'freemius_id',
            'gross' => $product_name . '_gross',
        );
    }

    return $hold;
}

/**
 * Set which segment a contact should be added to based on the plugin ID from Freemius.
 * 
 * This might look something like "CatsPlugin Segment" inside Mautic.
 * 
 * The Segment ID can be found in Mautic on the "Segments page". 
 * 
 * @return array 
 * @since  1.0.0
 */
function contactSegments(): array
{
    $product_id = productIDs();

    /**
     * Edit this array with your current plugin ids.
     */
    return array(
        $product_id['kikote'] => array( // Edit this ID with your plugin ID.
            2, // The segment ID to add the contact to.
        ),
        $product_id['dps'] => array(
            3
        ),
        $product_id['printus'] => array(
            4
        ),
    );
}

/**
 * Set the tags a contact should have based on the plugin ID from Freemius.
 * 
 * @return array 
 * @since  1.0.0
 */
function contactTags(): array
{
    $product_ids = productIDs();

    $hold = array();

    /**
     * Edit this array with your current plugin ids.
     */
    foreach ($product_ids as $product_name => $product_id) {
        $hold[$product_id] = array(
            'free-users-tags' => array(
                $product_name . '-free-user', // Edit these tags with the tag that should be set for Free users. You can add more tags to this sub array.
            ),
            'premium-users-tags' => array(
                $product_name . '-pro-user', // Edit these tags with the tag that should be set for Premium users. You can add more tags to this sub array.
            ),
            $product_name . '-user'  // You can set additional tags that you want attached to a contact other than the free/pro ones.
        );
    }

    return $hold;
}

/**
 * Email addresses that should be excluded from being added to mautic.
 * 
 * @return array 
 * @since  1.0.1
 */
function excludedEmails(): array
{
    return array(
        'plugins@soaringleads.com',
    );
}

/**
 * List of excluded domains.
 * 
 * Sites with these domains should not be processed. These are typically development sites so we shouldn't be updating
 * user tags or processing those webhooks.
 * 
 * @return array 
 * @since 1.1.1
 */
function excludedTLDs(): array
{
    return array(
        'local',
        'localhost',
        'host',
        'dev',
        'instawp.xyz',
        'instawp.co',
        'instawp.com',
        'instawp.link',
        'dev.cc',
        'test',
        'staging',
        'example',
        'invalid',
        'myftpupload.com',
        'cloudwaysapps.com',
        'wpsandbox.pro',
        'ngrok.io',
        'mystagingwebsite.com',
        'tempurl.host',
        'wpmudev.host',
        'websitepro-staging.com',
        'websitepro.hosting',
        'wpengine.com',
        'pantheonsite.io',
        'kinsta.com',
        'kinsta.cloud'
    );
}

/**
 * Check if a TLD is excluded.
 * 
 * @param string $url 
 * @return bool 
 * @since 1.1.1
 */
function isExcludedTLD(string $url): bool
{
    $excluded_tlds = excludedTLDs();

    $parts = explode('.', $url, 2);
    $tld = $parts[1] ?? array();

    return in_array($tld, $excluded_tlds, true);
}
