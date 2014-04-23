<?php

namespace Contactzilla\Api;

use Guzzle;

class Client
{
    const ERROR_MESSAGE = 'An unexpected error occurred communicating with Contactzilla. If the problem persists, please contact support.';

    protected $debug;
    protected $options;

    public function __construct(
        $accessToken,
        $options = null,
        $appInstallId = null,
        $apiHost = null,
        $debug = null,
        $contentType = null
    ) {
        $this->options = array(
            'accessToken' => null,
            'addressBook' => null,
            'appInstallId' => null,
            'apiHost' => null,
            'contentType' => false,
            'debug' => false
        );

        if (func_num_args() == 2 && is_array($options)) {
            $this->options = array_merge($this->options, $options);
        } else {
            $this->options['addressBook'] = $options ?: (isset($_GET['appContextAddressBook']) ? $_GET['appContextAddressBook'] : null);
            $this->options['appInstallId'] = $appInstallId ?: (isset($_GET['appContextInstallId']) ? $_GET['appContextInstallId'] : null);
            $this->options['apiHost'] = $apiHost;
            $this->options['contentType'] = $contentType;
            $this->options['debug'] = $debug;
        }
        
        $this->options['accessToken'] = $accessToken;

        $this->client = new Guzzle\Http\Client('https://' . ($this->options['apiHost'] ?: API_HOST));

        $this->setAccessToken($this->options['accessToken']);
        $this->setAddressBook($this->options['addressBook']);
        $this->setAppInstallId($this->options['appInstallId']);
        $this->setContentType($this->options['contentType']);
        $this->setDebug($this->options['debug']);

        $this->client->getEventDispatcher()->addListener('request.before_send', array($this, 'beforeRequestFixLegacyEndpoints'));
        $this->client->getEventDispatcher()->addListener('request.before_send', array($this, 'beforeSetContentType'));
    }

    public function get($endpoint, $params = array())
    {
        try {
            $response = $this->client->get($endpoint, array(), array('query' => $params))->send();
        } catch(Guzzle\Http\Exception\ClientErrorResponseException $e) {
            $message = $this->getDebug() ? 'API responded with: ' . $e->getResponse()->getBody() : self::ERROR_MESSAGE;

            throw new Guzzle\Http\Exception\ClientErrorResponseException($message);
        }

        return $response->json();
    }

    public function post($endpoint, $params = array())
    {
        try {
            $response = $this->client->post($endpoint, array(), $params)->send();
        } catch(Guzzle\Http\Exception\ClientErrorResponseException $e) {
            $message = $this->getDebug() ? 'API responded with: ' . $e->getResponse()->getBody() : self::ERROR_MESSAGE;

            throw new Guzzle\Http\Exception\ClientErrorResponseException($message);
        }

        return $response->json();
    }

    public function delete($endpoint, $params = array())
    {
        try {
            $response = $this->client->delete($endpoint, array(), array('query' => $params))->send();
        } catch(Guzzle\Http\Exception\ClientErrorResponseException $e) {
            $message = $this->getDebug() ? 'API responded with: ' . $e->getResponse()->getBody() : self::ERROR_MESSAGE;

            throw new Guzzle\Http\Exception\ClientErrorResponseException($message);
        }

        return $response->json();
    }

    public function call($endpoint, $params = array(), $method)
    {
        return $this->$method($endpoint, $params);
    }

    public function getContacts($params = array())
    {
        return $this->get('/address_books/' . $this->addressBook . '/contacts', $params);
    }

    /**
     * Gets user data for this application
     */
    public function getUserData($key = null)
    {
        $userData = $this->get($this->getUserDataUrl());

        if ($key !== null) {
            $userData = isset($userData[$key]) ? $userData[$key] : null;
        }

        return $userData;
    }

    /**
     * Saves user data against the application
     */
    public function setUserData($key, $value)
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
        return $this->setUserData($key, $value);
    }

    /**
     * @deprecated renamed
     */
    public function saveUserDataKeyValue($key, $value)
    {
        return $this->setUserData($key, $value);
    }

    /**
     * Delete user data for this application, all of it if no key is passed
     */
    public function deleteUserData($key = null)
    {
        $uri = $this->getUserDataUrl();

        if ($key !== null) {
            $uri .= '/key/' . $key;
        }

        $this->delete($uri);
    }

    public function getAccessToken()
    {
        return $this->accessToken;
    }

    public function setAccessToken($accessToken)
    {
        $this->accessToken = $accessToken;

        $this->client->setDefaultOption('query', array('access_token' => $accessToken));

        return $this;
    }

    public function getAddressBook()
    {
        return $this->addressBook;
    }

    public function setAddressBook($addressBook)
    {
        $this->addressBook = $addressBook;

        return $this;
    }

    public function getAppInstallId()
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

    public function setContentType($contentType)
    {
        $this->contentType = $contentType;
    }

    public function getContentType()
    {
        return $this->contentType;
    }

    public function getSabreDAVClient(array $args) {
        $client = new \Sabre\DAVClient\Client($args);

        $client->on('beforeRequest', function ($request) {
            $request->addHeaders(array(
                'Authorization' => 'Bearer ' . $this->getAccessToken(),
                'Expect' => null
            ));
        });

        if ($this->getDebug()) {
            $client->setVerifyPeer(false);
        }

        return $client;
    }

    /**
     * Allows for legacy requests (mostly in importers)
     * @todo correct these in importers and deprecate this functionality
     */
    public function beforeRequestFixLegacyEndpoints(Guzzle\Common\Event $event)
    {
        $request = $event['request'];

        if ($request->getPath() == '/contacts') {
             $request->setPath('/address_books/' . $this->getAddressBook() . '/contacts');
        }

        if ($request->getPath() == '/data/user') {
            $request->setPath($this->getUserDataUrl());
        }
    }

    public function beforeSetContentType(Guzzle\Common\Event $event)
    {
        if ($this->getContentType()) {
            $request = $event['request'];
            $request->setHeader('Content-Type', $this->getContentType());
        }
    }

    protected function getUserDataUrl() {
        return '/address_books/' . $this->getAddressBook() . '/app_install/' . $this->getAppInstallId() . '/data/user';
    }
}
