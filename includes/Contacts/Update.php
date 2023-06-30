<?php

/**
 * Class responsible for creating contacts..
 *
 * Author:          Uriahs Victor
 * Created on:      30/06/2023 (d/m/y)
 *
 * @link    https://uriahsvictor.com
 * @since   1.1.0
 * @package Contacts
 */

namespace CoachFreem\Contacts;

use CoachFreem\Contacts\Base as BaseContacts;

/**
 * Update a contact.
 * 
 * @package CoachFreem\Contacts
 * @since 1.1.0
 */
class Update extends BaseContacts
{

	/**
	 * The freemius user data.
	 * 
	 * @var   int
	 * @since 1.1.0
	 */
	private array $user_data;

	/**
	 * Class constructor.
	 * 
	 * @param  int $plugin_id 
	 * @return void 
	 * @since  1.0.0
	 */
	public function __construct(array $user_data)
	{
		parent::__construct();
		$this->user_data =  $user_data;
	}

	/**
	 * Updates a contact tags.
	 * 
	 * @param array $add_tags Tags to add.
	 * @param array $remove_tags Tags to remove.
	 * @return int 
	 * @since 1.1.0
	 */
	public function updateContactTags(array $add_tags = array(), array $remove_tags = array()): int
	{
		$email = $this->user_data['email'];
		$contact_details = $this->findContactDetailsByEmail($email);
		$contact_id = $contact_details['id'];
		$contact_current_tags = array_column($contact_details['tags'], 'tag');

		foreach ($contact_current_tags as $key => &$tag) {
			if (in_array($tag, $remove_tags)) {
				$tag = '-' . $tag; // dash infront tag signifies that it should be removed.
			}
		}
		unset($tag);

		$new_tags = array_unique(array_merge($contact_current_tags, $add_tags));

		$this->client->edit($contact_id, array(
			'tags' => $new_tags
		));

		return $contact_id;
	}
}
