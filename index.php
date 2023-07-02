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
 * @version 1.1.2
 */

use CoachFreem\Contacts\Create as CreateContact;
use CoachFreem\Contacts\Update as UpdateContact;
use Google\CloudFunctions\FunctionsFramework;
use Psr\Http\Message\ServerRequestInterface;


// Register the function with Functions Framework.
// This enables omitting the `FUNCTIONS_SIGNATURE_TYPE=http` environment
// variable when deploying. The `FUNCTION_TARGET` environment variable should
// match the first parameter.
FunctionsFramework::http('init', 'init');

function init(ServerRequestInterface $request): string
{

    $body = $request->getBody()->getContents();
    $body = json_decode($body, true);

    $user_data = $body['objects']['user'] ?? '';

    if (empty($user_data)) {
        return 'no user data';
    }

    $install = $body['objects']['install'] ?? array();
    $user_data['is_premium'] = $install['is_premium'] ?? false; // Using this to decide how to tag contacts.

    $plugin_id = $body['plugin_id'] ?? '';

    if (empty($plugin_id)) {
        return 'plugin id empty';
    }

    $event_type = $body['type'] ?? '';

    /**
     * Bail if email is excluded.
     */
    $user_email = $user_data['email'] ?? '';
    if (in_array($user_email, excludedEmails()) || empty($user_email)) {
        return 'excluded email';
    }

    /**
     * Bail if TLD is excluded.
     */
    $domain = $install['url'] ?? '';
    if (isExcludedTLD($domain)) {
        return "development domain";
    }

    $contactCreate = new CreateContact($plugin_id);
    $contactUpdate = new UpdateContact($user_data);
    $product = array_flip(productIDs())[$plugin_id] ?? '';

    /**
     * Bail if the plugin ID received is not in the array provided in productIDs()
     */
    if (empty($product)) {
        return "product id not found in array";
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

    /**
     * This will return null if: 
     * 
     * The username and password set for the Client is wrong. 
     * The URL you set for the Mautic API is wrong.
     * The contact email is in the excluded list.
     */
    return json_encode($id);
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
    $product_id = productIDs();

    /**
     * Edit this array with your current plugin ids.
     */
    return array(
        $product_id['kikote'] => array(
            'id' => 'freemius_id',
            'gross' => 'kikote_gross',
        ),
        $product_id['dps'] => array(
            'id' => 'freemius_id',
            'gross' => 'dps_gross',
        ),
        $product_id['printus'] => array(
            'id' => 'freemius_id',
            'gross' => 'printus_gross',
        ),
    );
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
    $product_id = productIDs();

    /**
     * Edit this array with your current plugin ids.
     */
    return array(
        $product_id['kikote'] => array( // Edit this ID with your plugin ID
            'free-users-tags' => array(
                'kikote-free-user', // Edit these tags with the tag that should be set for Free users. You can add more tags to this sub array.
            ),
            'premium-users-tags' => array(
                'kikote-pro-user' // Edit these tags with the tag that should be set for Premium users. You can add more tags to this sub array.
            ),
            'kikote-user', // You can set additional tags that you want attached to a contact other than the free/pro ones.
        ),
        $product_id['dps'] => array(
            'free-users-tags' => array(
                'dps-free-user'
            ),
            'premium-users-tags' => array(
                'dps-pro-user'
            ),
            'dps-user'
        ),
        $product_id['printus'] => array(
            'free-users-tags' => array(
                'printus-free-user'
            ),
            'premium-users-tags' => array(
                'printus-pro-user'
            ),
            'printus-user'
        ),
    );
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
