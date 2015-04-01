<?php

namespace Contactzilla\Api;

use Guzzle;
use CommerceGuys\Guzzle\Plugin\Oauth2\Oauth2Plugin;
use CommerceGuys\Guzzle\Plugin\Oauth2\GrantType\RefreshToken;

class Client
{
    const API_HOST = 'api.contactzilla.com';
    const AUTH_HOST = 'hq.contactzilla.com';
    const ERROR_MESSAGE = 'An unexpected error occurred communicating with Contactzilla. If the problem persists, please contact support.';

    protected $debug;

    protected $defaults = array(
        'accessToken'  => null,
        'addressBook'  => null,
        'appInstallId' => null,
        'apiHost'      => null,
        'contentType'  => false,
        'debug'        => false
    );

    public function __construct(
        $accessTokenOrOptions,
        $addressBook = null,
        $appInstallId = null,
        $apiHost = null,
        $debug = null,
        $contentType = null
    ) {
        // BC: allow an options array or multiple arguments to maintain BC
        //     any new implementations should use the options array, we'll
        //     deprecate the latter at some point
        if (is_array($accessTokenOrOptions)) {
            if (func_num_args() == 1) {
                $options = array_merge($this->defaults, $accessTokenOrOptions);
            } else {
                throw new \Exception('Invalid arguments: either pass in an options array or multiple arguments, not both.');
            }
        } else {
            $options = array_merge($this->defaults, array(
                'accessToken'  => $accessTokenOrOptions,
                'addressBook'  => $addressBook,
                'apiHost'      => $apiHost,
                'appInstallId' => $appInstallId,
                'contentType'  => $contentType,
                'debug'        => $debug
            ));
        }

        if (!$options['addressBook'] && isset($_GET['appContextAddressBook'])) {
            $options['addressBook'] = $_GET['appContextAddressBook'];
        }

        if (!$options['appInstallId'] && isset($_GET['appContextInstallId'])) {
            $options['appInstallId'] = $_GET['appContextInstallId'];
        }

        $this->client = new Guzzle\Http\Client('https://' . ($options['apiHost'] ?: self::API_HOST));

        if (array_key_exists('client_id', $options)) {
            $this->oauth2Client = new Guzzle\Http\Client('https://' . ($options['authHost'] ?: self::AUTH_HOST) . '/oauth2/grant');
            $this->oauth2Client->setDefaultOption('verify', false);

            $grantType = new RefreshToken($this->oauth2Client, $options);
            $refreshTokenGrantType = new RefreshToken($this->oauth2Client, $options);
            $this->oauth2 = new Oauth2Plugin($grantType, $refreshTokenGrantType);

            $this->client->addSubscriber($this->oauth2);
        }

        $this->setRefreshToken($options['refreshToken']);
        $this->setAccessToken($options['accessToken']);
        $this->setAddressBook($options['addressBook']);
        $this->setAppInstallId($options['appInstallId']);
        $this->setContentType($options['contentType']);
        $this->setDebug($options['debug']);

        $this->client->getEventDispatcher()->addListener('request.before_send', array($this, 'beforeRequestFixLegacyEndpoints'));
        $this->client->getEventDispatcher()->addListener('request.before_send', array($this, 'beforeSetContentType'));
    }

    public function get($endpoint, $params = array())
    {
        try {
            $response = $this->client->get($endpoint, array(), array('query' => $params))->send();
        } catch (Guzzle\Http\Exception\ClientErrorResponseException $e) {
            $message = $this->getDebug() ? 'API responded with: ' . $e->getResponse()->getBody() : self::ERROR_MESSAGE;

            throw new Guzzle\Http\Exception\ClientErrorResponseException($message);
        }

        return $response->json();
    }

    public function post($endpoint, $params = array())
    {
        try {
            $response = $this->client->post($endpoint, array(), $params)->send();
        } catch (Guzzle\Http\Exception\ClientErrorResponseException $e) {
            $message = $this->getDebug() ? 'API responded with: ' . $e->getResponse()->getBody() : self::ERROR_MESSAGE;

            throw new Guzzle\Http\Exception\ClientErrorResponseException($message);
        }

        return $response->json();
    }

    public function delete($endpoint, $params = array())
    {
        try {
            $response = $this->client->delete($endpoint, array(), array('query' => $params))->send();
        } catch (Guzzle\Http\Exception\ClientErrorResponseException $e) {
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

    public function getRefreshToken()
    {
        return $this->oauth2->getRefreshToken();
    }

    public function setRefreshToken($refreshToken)
    {
        $this->refreshToken = $refreshToken;

        $this->oauth2->setRefreshToken($refreshToken);

        return $this;
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

    public function getSabreDAVClient(array $args)
    {
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

    protected function getUserDataUrl()
    {
        return '/address_books/' . $this->getAddressBook() . '/app_install/' . $this->getAppInstallId() . '/data/user';
    }
}
