<?php
/**
 * Class responsible for creating contacts.
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
use CoachFreem\Segments\Base as BaseSegments;

/**
 * Create a contact.
 * 
 * @package Includes
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
     * @param int $plugin_id 
     * @return void 
     * @since 1.0.0
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
    private function mapWebHookData(array $user_data) : array
    {
        $mautic_field_list = $this->client->getFieldList();
        $plugin_custom_mappings = $this->custom_mappings[$this->plugin_id];

        $hold = array();

        foreach($mautic_field_list as $index => $data){

            $field_name = $data['alias'] ?? '';

            if(empty($field_name)) {
                continue;
            }
     
            $hold[$field_name] = $user_data[$field_name] ?? '';

            $mapping = array_search($field_name, $plugin_custom_mappings, true);
            if(!empty($mapping) ) {
                $hold[$field_name] = $user_data[$mapping];
            }
         
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
    private function addContactToMautic( $user_data ): ?int
    {
        $contact_data = $this->mapWebHookData($user_data);
        $contact_data['tags'] = $this->prepareContactTags($user_data);

        $response = $this->client->create($contact_data);

        return $response[$this->client->itemName()]['id'] ?? null;
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

        foreach($segments as $segment_id){
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
    public function setSegments(array $segments): void
    {
        $this->segments = $segments;
    }

    /**
     * Set our tags.
     * 
     * @param  mixed $tags 
     * @return void 
     * @since  1.0.0
     */
    public function setTags(array $tags): void
    {
        $this->tags = $tags;
    }

    /**
     * Set our custom mappings.
     * 
     * @param  mixed $mapping 
     * @return void 
     */
    public function setCustomMappings(array $mapping): void
    {
        $this->custom_mappings = $mapping;
    }

    /**
     * Create a contact in the Mautic install.
     * 
     * @param  array $data 
     * @param  array $custom_mappings 
     * @return void 
     * @since  1.0.0
     */
    public function add(array $user_data, array $custom_mappings = array()): ?int
    {

        if(empty($user_data['is_marketing_allowed']) ) {
            return null; // Only opted in contacts please...
        }

        $id = $this->addContactToMautic($user_data);

        if(empty($id)) {
            return null;
        }

        $this->addContactToSegment($id);

        return $id;
    }
    

}