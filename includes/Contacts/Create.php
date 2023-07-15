<?php

/**
 * Class responsible for creating Contacts.
 *
 * Author:          Uriahs Victor
 * Created on:      28/03/2023 (d/m/y)
 *
 * @link    https://uriahsvictor.com
 * @since   1.0.0
 * @package Contacts
 */

namespace CoachFreem\Contacts;

use CoachFreem\Contacts\Base as BaseContacts;
use CoachFreem\Logger;
use CoachFreem\Segments\Base as BaseSegments;

/**
 * Create a contact.
 * 
 * @package @package CoachFreem\Contacts
 * @since   1.0.0
 */
class Create extends BaseContacts
{

    /**
     * Segments that the contact should be added to.
     * 
     * @var   array
     * @since 1.0.0
     */
    private array $segments;

    /**
     * Tags that should be attached to a contact.
     * 
     * @var   array
     * @since 1.0.0
     */
    private array $tags;

    /**
     * Freemius Webhook fields that should be mapped to custom Mautic fields.
     * 
     * @var array
     */
    private array $custom_mappings;

    /**
     * The current plugin that caused the webhook.
     * 
     * @var   int
     * @since 1.0.0
     */
    private int $plugin_id;

    /**
     * Class constructor.
     * 
     * @param  int $plugin_id 
     * @return void 
     * @since  1.0.0
     */
    public function __construct(int $plugin_id)
    {
        parent::__construct();
        $this->plugin_id =  $plugin_id;
    }

    /**
     * Map the webhook data from Freemius to the fields supported by Mautic.
     * 
     * @param  array $webhook_data 
     * @param  array $custom_mappings 
     * @return array 
     * @since  1.0.0
     */
    private function mapWebHookData(array $user_data): array
    {

        $plugin_custom_mappings = $this->custom_mappings[$this->plugin_id];

        $hold = array();

        foreach ($plugin_custom_mappings as $key => $mapping) {
            $hold[$mapping] = $user_data[$key];
        }

        return $hold;
    }

    /**
     * Adds the contact to Mautic.
     * 
     * @param  mixed $user_data 
     * @return null|int 
     * @since  1.0
     */
    private function addContactToMautic($user_data): ?int
    {
        $contact_data = $this->mapWebHookData($user_data);
        $contact_data['tags'] = $this->prepareContactTags($user_data);

        // If contact exists already then update values.
        $existing_contact = $this->findContactDetailsByEmail($user_data['email']);
        if ($existing_contact) {
            $contact_details = $this->client->edit($existing_contact['id'], $contact_data);
            return $contact_details['contact']['id'] ?? null;
        }

        $contact_data['email'] = $user_data['email'];
        $response = $this->client->create($contact_data);

        $mautic_id = $response[$this->client->itemName()]['id'] ?? null;

        if (empty($mautic_id)) {
            Logger::log("Mautic response does not contain user ID. Response received: \n\n" . json_encode($response, JSON_PRETTY_PRINT), $user_data);
        }

        return $mautic_id;
    }

    /**
     * Attach tags to a contact data before sending to Mautic.
     * 
     * @return void 
     * @since  1.0.0
     */
    private function prepareContactTags(array $user_data): array
    {
        $all_tags = $this->tags[$this->plugin_id];

        $premium_tags = $all_tags['premium-users-tags'] ?? array();
        unset($all_tags['premium-users-tags']); // remove premium tags once you've got hold of them.

        $free_tags = $all_tags['free-users-tags'] ?? array();
        unset($all_tags['free-users-tags']); // remove free tags once you've got hold of them.

        $applicable_tags = ($user_data['is_premium']) ?  $premium_tags : $free_tags;

        return array_merge($applicable_tags, $all_tags); // Add any miscellenous tags that was set in our tags array.
    }

    /**
     * Adds a contact to a segment created in Mautic.
     * 
     * @param  int $contact_id 
     * @return void 
     * @since  1.0.0
     */
    private function addContactToSegment(int $contact_id): void
    {
        $segments = $this->segments[$this->plugin_id];
        $segments_client = (new BaseSegments)->client;

        foreach ($segments as $segment_id) {
            $segments_client->addContact($segment_id, $contact_id);
        }
    }

    /**
     * Set our segments.
     * 
     * @param  array $segments 
     * @return void 
     * @since  1.0.0
     */
    public function setSegments(array $segments): object
    {
        $this->segments = $segments;
        return $this;
    }

    /**
     * Set our tags.
     * 
     * @param  mixed $tags 
     * @return void 
     * @since  1.0.0
     */
    public function setTags(array $tags): object
    {
        $this->tags = $tags;
        return $this;
    }

    /**
     * Set our custom mappings.
     * 
     * @param  mixed $mapping 
     * @return object 
     */
    public function setCustomMappings(array $mapping): object
    {
        $this->custom_mappings = $mapping;
        return $this;
    }

    /**
     * Create a contact in the Mautic install.
     * 
     * @param  array $user_data 
     * @return void 
     * @since  1.0.0
     */
    public function add(array $user_data): ?int
    {

        if (empty($user_data['is_marketing_allowed'])) {
            return null; // Only opted in contacts please...
        }

        $id = $this->addContactToMautic($user_data);
        if (empty($id)) {
            Logger::log("Empty contact ID received from Mautic API.");
            return null;
        }

        $this->addContactToSegment($id);
        return $id;
    }
}
