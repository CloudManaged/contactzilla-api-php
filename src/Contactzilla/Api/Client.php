<?php

namespace Contactzilla\Api;

use Guzzle\Http\Client as GuzzleClient;
use Guzzle\Http\Exception\ClientErrorResponseException;

class Client
{
    public function __construct(
        $access_token,
        $appId = false,
        $appSecret = false,
        $addressBook = false,
        $appInstallId = false,
        $apiHost = false
    ) {
        $this->appId = $appId ?: APP_ID;
        $this->appSecret = $appSecret ?: APP_SECRET;
        $this->apiHost = $apiHost ?: API_HOST;
        $this->addressBook = $addressBook ?: $_GET['appContextAddressBook'];
        $this->appInstallId = $appInstallId ?: $_GET['appContextInstallId'];
        $this->access_token = $access_token;

        $this->client = new GuzzleClient('https://' . $this->apiHost);
        $this->client->setDefaultOption('query', ['access_token' => $access_token]);

        if (APPLICATION_ENV == 'dev') {
            $this->client->setDefaultOption('verify', false);
        }
    }

    public function post($endpoint, $params = [])
    {
        try {
            // This is a bit of a fudge to prevent having to amend all calls to /contacts
            if ($endpoint == '/contacts') {
                 $endpoint = '/address_books/' . $this->addressBook . '/contacts';
            }

            if ($endpoint == '/data/user') {
                $endpoint = $this->getDataUrl();
            }

            $response = $this->client->post($endpoint, [], $params)->send();

            return $response->json();
        } catch(ClientErrorResponseException $e) {
            $message = APPLICATION_ENV=='dev' ? 'Api responded with: ' . $e->getResponse()->getBody() :
                'An unexpected error occurred communicating with Contactzilla. If the problem persists, please contact support.';

            throw new ClientErrorResponseException($message);
        }
    }

    public function get($endpoint, $params = [])
    {
        try {
            // This is a bit of a fudge to prevent having to amend all calls to /data/user
            if ($endpoint == '/data/user') {
                $endpoint = $this->getDataUrl();
            }

            $response = $this->client->get($endpoint, [], [
                'query' => $params
            ])->send();

            return $response->json();
        } catch(ClientErrorResponseException $e) {
            $message = APPLICATION_ENV=='dev' ? 'Api responded with: ' . $e->getResponse()->getBody() :
                'An unexpected error occurred communicating with Contactzilla. If the problem persists, please contact support.';
            throw new ClientErrorResponseException($message);
        }
    }

    public function call($endpoint, $params = [], $method) {
        return $this->$method($endpoint, $params);
    }

    /**
     * Saves user data against the application.
     */
    public function saveDataKeyValue($key, $value)
    {
        $this->post($this->getDataUrl(), [
            'body' => json_encode([
                ['key' => $key, 'value' => $value]
            ])
        ]);
    }

    private function getDataUrl() {
        return '/address_books/' . $this->addressBook . '/app_install/' . $this->appInstallId . '/data/user';
    }
}
