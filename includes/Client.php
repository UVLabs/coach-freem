<?php

/**
 * Mautic Client creation class.
 *
 * Author:          Uriahs Victor
 * Created on:      28/03/2023 (d/m/y)
 *
 * @link    https://uriahsvictor.com
 * @since   1.0.0
 * @package Includes
 */

namespace CoachFreem;

use Mautic\Api\Api;
use Mautic\Auth\ApiAuth;
use Mautic\MauticApi;
use Dotenv\Dotenv;

class Client
{

    /**
     * The purpose of the client object.
     *  
     * @var   string
     * @since 1.0.0
     */
    private string $context;

    /**
     * Set context.
     * 
     * @param  string $context 
     * @return void 
     * @since  1.0.0
     */
    public function __construct(string $context)
    {
        $this->context = $context;
    }

    /**
     * 
     * @param  mixed $context 
     * @return Api|null 
     */
    private function setClient($context)
    {

        $dotenv = Dotenv::createImmutable(ABSPATH);
        $dotenv->load();
        $settings = [
            'userName'   => $_ENV['MAUTICAPIUSER'],
            'password'   => $_ENV['MAUTICAPIPW']
        ];

        $endpoint = $_ENV['MAUTICAPIURL'];

        try {
            $initAuth = new ApiAuth();
            $auth     = $initAuth->newAuth($settings, 'BasicAuth');

            $api        = new MauticApi();
            return $api->newApi($context, $auth, $endpoint);
        } catch (\Throwable $th) {
            Logger::log('There was an issue setting up the Mautic API client. Error: ' . $th->getMessage());
        }

        return null;
    }

    /**
     * Get an instance of the client.
     * 
     * @param  mixed $context 
     * @return Api 
     */
    public function getClient(): Api
    {
        return $this->setClient($this->context);
    }
}
