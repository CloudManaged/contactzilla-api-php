<?php

namespace Contactzilla\Api;

use Guzzle;

class Client
{
    const ERROR_MESSAGE = 'An unexpected error occurred communicating with Contactzilla. If the problem persists, please contact support.';

    public function __construct(
        $access_token,
        $addressBook = false,
        $appInstallId = false,
        $apiHost = false,
        $debug = false
    ) {
        $this->client = new Guzzle\Http\Client('https://' . ($apiHost ?: API_HOST));

        $this->setAccessToken($access_token);
        $this->setAddressBook($addressBook ?: (isset($_GET['appContextAddressBook']) ? $_GET['appContextAddressBook'] : null));
        $this->setAppInstallId($appInstallId ?: (isset($_GET['appContextInstallId']) ? $_GET['appContextInstallId'] : null));
        $this->setDebug($debug);

        $this->client->getEventDispatcher()->addListener('request.before_send', array($this, 'beforeRequestFixLegacyEndpoints'));
    }

    public function get($endpoint, $params = array())
    {
        try {
            $response = $this->client->get($endpoint, array(), array('query' => $params))->send();
        } catch(Guzzle\Http\Exception\ClientErrorResponseException $e) {
            $message = $this->debug ? 'API responded with: ' . $e->getResponse()->getBody() : self::ERROR_MESSAGE;

            throw new Guzzle\Http\Exception\ClientErrorResponseException($message);
        }

        return $response->json();
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

    public function call($endpoint, $params = array(), $method)
    {
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
     * @deprecated renamed
     */
    public function saveDataKeyValue($key, $value)
    {
        return $this->saveUserDataKeyValue($key, $value);
    }

    public function getAccessToken($access_token)
    {
        return $this->access_token;
    }

    public function setAccessToken($access_token)
    {
        $this->access_token = $access_token;

        $this->client->setDefaultOption('query', array('access_token' => $access_token));

        return $this;
    }

    public function getAddressBook($addressBook)
    {
        return $this->addressBook;
    }

    public function setAddressBook($addressBook)
    {
        $this->addressBook = $addressBook;

        return $this;
    }

    public function getAppInstallId($appInstallId)
    {
        return $this->appInstallId;
    }

    public function setAppInstallId($appInstallId)
    {
        $this->appInstallId = $appInstallId;

        return $this;
    }

    public function getDebug()
    {
        return $this->debug;
    }

    public function setDebug($debug)
    {
        $this->debug = $debug;

        if ($this->debug) {
            $this->client->setDefaultOption('verify', false);
        }

        return $this;
    }

    /**
     * Allows for legacy requests (mostly in importers)
     * @todo correct these in importers and deprecate this functionality
     */
    public function beforeRequestFixLegacyEndpoints(Guzzle\Common\Event $event)
    {
        $request = $event['request'];

        if ($request->getPath() == '/contacts') {
             $request->setPath('/address_books/' . $this->addressBook . '/contacts');
        }

        if ($request->getPath() == '/data/user') {
            $request->setPath($this->getUserDataUrl());
        }
    }

    protected function getUserDataUrl() {
        return '/address_books/' . $this->addressBook . '/app_install/' . $this->appInstallId . '/data/user';
    }
}
