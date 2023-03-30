<?php
/**
 * Base class for Segments.
 *
 * Author:          Uriahs Victor
 * Created on:      28/03/2023 (d/m/y)
 *
 * @link    https://uriahsvictor.com
 * @since   1.0.0
 * @package Segments
 */

namespace CoachFreem\Segments;

use CoachFreem\Client;

/**
 * Base segments class.
 * 
 * Further segments classes can possibly extend this one to possibly create a custom segment based on Freemius webhook data. 
 * 
 * @package CoachFreem\Segments
 * @since   1.0.0
 */
class Base
{

    /**
     * Segments context.
       *
     * @since 1.0.0
     */
    const CONTEXT = 'segments';

    /**
     * Instance of Mautic API client.
     * 
     * @var   \Mautic\Api\Segments
     * @since 1.0.0
     */
    public \Mautic\Api\Segments $client;

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
}