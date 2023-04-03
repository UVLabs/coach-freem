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
 */

use CoachFreem\Contacts\Create as CreateContact;
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

    if(empty($user_data)) {
        return 'no user data';
    }

    $user_data['is_premium'] = $body['objects']['install']['is_premium'] ?? false; // Using this to decide how to tag contacts.

    $plugin_id = $body['plugin_id'] ?? '';

    if(empty($plugin_id)) {
        return 'plugin id empty';
    }
    
    $event_type = $body['type'] ?? '';

    switch ($event_type) {
    case 'install.installed':

        $custom_mappings = customContactDataMappings();
        $segments = contactSegments();
        $tags = contactTags();
        $excluded_emails = excludedEmails();

        $contactCreate = new CreateContact($plugin_id);
        $id = $contactCreate->setCustomMappings($custom_mappings)
            ->setSegments($segments)
            ->setTags($tags)
            ->add($user_data, $excluded_emails);

        break;
    case 'install.deactivated':
    case 'install.uninstalled':
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
 * Custom mappings for Freemius data sent to Mautic API.
 * 
 * This is useful if you have a custom field inside Mautic that isn't the same name as the one being sent by Freemius. 
 * Use the Plugin ID to differenciate mappings if you have multiple Freemius plugins, and you are sending all of their webhooks Coach Freem. 
 * 
 * Note that for "gross" in this example, I am mapping it to "kikote_gross" which is a custom field I created in Mautic to track the Gross of that plugin for a contact.
 * 
 * @return array 
 * @since  1.0.0
 */
function customContactDataMappings(): array
{

    return array(
        '8507' => array( // Edit this ID with your plugin ID.
            'id' => 'freemius_id',
            'gross' => 'kikote_gross',
        ),
        '11538' => array( // Add other plugins you own.
            'id' => 'freemius_id',
            'gross' => 'dps_gross',
        ),
        // You can add further plugin IDs and their mappings.
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

    return array (
        '8507' => array( // Edit this ID with your plugin ID.
            2, // The segment ID to add the contact to.
        ),
        '11538' => array( // Add other plugins you own.
            3
        ),
        // You can add further plugin IDs and their mappings.
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
    return array(
        '8507' => array( // Edit this ID with your plugin ID
            'free-users-tags' => array(
                'kikote-free-user', // Edit these tags with the tag that should be set for Free users. You can add more tags to this sub array.
            ),
            'premium-users-tags' => array(
                'kikote-pro-user' // Edit these tags with the tag that should be set for Premium users. You can add more tags to this sub array.
            ),
            'kikote-user', // You can set additional tags that you want attached to a contact other than the free/pro ones.
        ),
        '11538' => array( // Add other plugins you own.
            'free-users-tags' => array(
                'dps-free-user'
            ),
            'premium-users-tags' => array(
                'dps-pro-user'
            ),
            'dps-user'
        ),
        // You can add further plugin IDs and their mappings.
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
    );
}

