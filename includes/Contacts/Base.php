<?php

/**
 * Base class for Contacts.
 *
 * Author:          Uriahs Victor
 * Created on:      28/03/2023 (d/m/y)
 *
 * @link    https://uriahsvictor.com
 * @since   1.0.0
 * @package Contacts
 */

namespace CoachFreem\Contacts;

use CoachFreem\Client;

/**
 * Base contacts class.
 * 
 * @package CoachFreem\Contacts
 * @since   1.0.0
 */
class Base
{

  /**
   * Contacts context.
   *
   * @since 1.0.0
   */
  const CONTEXT = 'contacts';

  /**
   * Instance of Mautic API client.
   * 
   * @var   \Mautic\Api\Contacts
   * @since 1.0.0
   */
  protected \Mautic\Api\Contacts $client;

  /**
   * Contructor.
   * 
   * @return void 
   * @since  1.0.0
   */
  public function __construct()
  {
    $this->client = (new Client(self::CONTEXT))->getClient();
  }

  /**
   * Get a contact details using email address.
   * 
   * @param array $user_data 
   * @return array 
   * @since 1.1.0
   */
  protected function findContactDetailsByEmail(string $email): array
  {
    $response = $this->client->getList($email);
    $id = array_key_first($response['contacts']);
    return $response['contacts'][$id] ?? array();
  }
}
