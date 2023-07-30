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
use CoachFreem\Logger;
use Exception;

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
   * Logging class.
   * 
   * @var Logger
   * @since 1.2.0
   */
  protected Logger $logger;

  /**
   * Contructor.
   * 
   * @return void 
   * @since  1.0.0
   */
  public function __construct()
  {
    $this->client = (new Client(self::CONTEXT))->getClient();
    $this->logger = new Logger;
  }

  /**
   * Get a contact details using email address.
   * 
   * @param int $id The freemius ID. 
   * @return array 
   * @since 1.1.0
   * @throws Exception 
   */
  protected function findContactDetailsByFreemiusID(int $freemius_id): array
  {
    try {
      $response = $this->client->getList(
        "freemius_id:{$freemius_id}",
        0,
        1
      );
    } catch (\Throwable $th) {
      Logger::log("Error checking existing contact. Freemius ID: $freemius_id, Error Msg: " . $th->getMessage());
      return array();
    }
    if (!is_array($response) && empty($response)) {
      return array();
    }
    $id = array_key_first($response['contacts']);
    return $response['contacts'][$id] ?? array();
  }
}
