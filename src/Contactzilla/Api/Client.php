<?php

namespace Contactzilla\Api;

use Guzzle\Common\Collection;
use Guzzle\Plugin\Oauth\OauthPlugin;
use Guzzle\Service\Client as GuzzleClient;
use Guzzle\Service\Description\ServiceDescription;

/**
 * A simple Contactzilla API client
 */
class Client extends GuzzleClient
{
    public static function factory($config = array())
    {
        // Provide a hash of default client configuration options
        $default = array('base_url' => 'https://api.localtesting.contactzilla.com');

        // The following values are required when creating the client
        $required = array(
            'base_url',
            'consumer_key',
            'consumer_secret',
            'token',
            'token_secret'
        );

        // Merge in default settings and validate the config
        $config = Collection::fromConfig($config, $default, $required);

        // Create a new Contactzilla client
        $client = new self($config->get('base_url'), $config);

        // Ensure that the OauthPlugin is attached to the client
        $client->addSubscriber(new OauthPlugin($config->toArray()));

        // Set the service description
        $client->setDescription(ServiceDescription::factory(__DIR__.'/contactzilla.json'));

        return $client;
    }
}
