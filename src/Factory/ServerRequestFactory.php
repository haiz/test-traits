<?php

namespace Haiz\TestTrait\Factory;

use InvalidArgumentException;
use Http\Cookies;
use Http\Headers;
use Http\Request;

use function current;
use function explode;
use function fopen;
use function in_array;
use function is_string;

class ServerRequestFactory
{
    /**
     * @var StreamFactory
     */
    protected $streamFactory;

    /**
     * @var UriFactory
     */
    protected $uriFactory;

    public function __construct()
    {
        $this->streamFactory = new StreamFactory();
        $this->uriFactory = new UriFactory();
    }

    /**
     * {@inheritdoc}
     */
    public function createServerRequest($method, $uri, $serverParams = [])
    {
        $uri = $this->uriFactory->createUri($uri);

        $body = $this->streamFactory->createStream();
        $headers = new Headers();
        $cookies = [];

        if (!empty($serverParams)) {
            $headers = Headers::createFromGlobals();
            $cookies = Cookies::parseHeader($headers->getHeader('Cookie', []));
        }

        return new Request($method, $uri, $headers, $cookies, $serverParams, $body);
    }
}
