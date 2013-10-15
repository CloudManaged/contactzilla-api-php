<?php

namespace Contactzilla\Api\Client\Tests;

use Guzzle\Service\Builder\ServiceBuilder;

class ClientTest extends \PHPUnit_Framework_TestCase
{
    public function testVersion()
    {
    	// Create a service builder and provide client configuration data
		$builder = ServiceBuilder::factory(SERVICE_BUILDER_PATH);

		// Get the client from the service builder by name
		$client = $builder->get('Contactzilla');

		$response = $client->get('/')->send();

		$this->assertEquals('0.0.1', $response->json()['api_version']);
    }
}