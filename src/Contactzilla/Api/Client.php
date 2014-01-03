<?php

namespace Contactzilla\Api;

use Guzzle;

class Client
{
    const ERROR_MESSAGE = 'An unexpected error occurred communicating with Contactzilla. If the problem persists, please contact support.';

    public function __construct(
        $access_token,
        $appId = false,
        $appSecret = false,
        $addressBook = false,
        $appInstallId = false,
        $apiHost = false,
        $debug = false
    ) {
        $this->appId = $appId ?: APP_ID;
        $this->appSecret = $appSecret ?: APP_SECRET;
        $this->addressBook = $addressBook ?: $_GET['appContextAddressBook'];
        $this->appInstallId = $appInstallId ?: $_GET['appContextInstallId'];
        $this->apiHost = $apiHost ?: API_HOST;
        $this->access_token = $access_token;

        $this->client = new Guzzle\Http\Client('https://' . $this->apiHost);
        $this->client->setDefaultOption('query', array('access_token' => $access_token));

        $this->debug = $debug ?: APPLICATION_ENV == 'dev';

        if ($this->debug) {
            $this->client->setDefaultOption('verify', false);
        }

        $this->client->getEventDispatcher()->addListener('request.before_send', array($this, 'beforeRequestFixLegacyEndpoints'));
    }

    public function post($endpoint, $params = array())
    {
        try {
            $response = $this->client->post($endpoint, array(), $params)->send();
        } catch(Guzzle\Http\Exception\ClientErrorResponseException $e) {
            $message = $this->debug ? 'API responded with: ' . $e->getResponse()->getBody() : self::ERROR_MESSAGE;

            throw new Guzzle\Http\Exception\ClientErrorResponseException($message);
        }

        return $response->json();
    }

    public function get($endpoint, $params = array())
    {
        try {
            $response = $this->client
                ->get($endpoint, array(), array(
                    'query' => $params
                ))
                ->send()
            ;

            return $response->json();
        } catch(Guzzle\Http\Exception\ClientErrorResponseException $e) {
            $message = $this->debug ? 'API responded with: ' . $e->getResponse()->getBody() : self::ERROR_MESSAGE;

            throw new Guzzle\Http\Exception\ClientErrorResponseException($message);
        }
    }

    public function call($endpoint, $params = array(), $method) {
        return $this->$method($endpoint, $params);
    }

    /**
     * Gets user data for this application
     */
    public function getUserData()
    {
        return $this->get($this->getUserDataUrl());
    }

    /**
     * Saves user data against the application
     */
    public function saveUserDataKeyValue($key, $value)
    {
        return $this->post($this->getUserDataUrl(), array(
            'body' => json_encode(array(
                array('key' => $key, 'value' => $value)
            ))
        ));
    }

    /**
     * @alias saveUserDataKeyValue
     * @deprecated renamed
     */
    public function saveDataKeyValue($key, $value)
    {
        return $this->saveUserDataKeyValue($key, $value);
    }

    /**
     * Allows for legacy requests (mostly in importers)
     * @todo correct these in importers and deprecate this functionality
     */
    public function beforeRequestFixLegacyEndpoints(Guzzle\Common\Event $event)
    {
        $request = $event['request'];

        if ($request->getUrl() == '/contacts') {
             $request->setUrl('/address_books/' . $this->addressBook . '/contacts');
        }

        if ($request->getUrl() == '/data/user') {
            $request->setUrl($this->getUserDataUrl());
        }
    }

    protected function getUserDataUrl() {
        return '/address_books/' . $this->addressBook . '/app_install/' . $this->appInstallId . '/data/user';
    }
}
