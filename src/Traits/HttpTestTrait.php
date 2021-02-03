<?php

namespace Haiz\TestTrait\Traits;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;

/**
 * HTTP Test Trait.
 */
trait HttpTestTrait
{
    /**
     * Create a server request.
     *
     * @param string $method The HTTP method
     * @param string|UriInterface $uri The URI
     * @param array<mixed> $serverParams The server parameters
     *
     * @return ServerRequestInterface The request
     */
    protected function createRequest($method, $uri, $serverParams = [])
    {
        return (new \Factory\ServerRequestFactory())->createServerRequest($method, $uri, $serverParams);
    }

    /**
     * Create a form request.
     *
     * @param string $method The HTTP method
     * @param string|UriInterface $uri The URI
     * @param array<mixed>|null $data The form data
     *
     * @return ServerRequestInterface
     */
    protected function createFormRequest($method, $uri, $data = null)
    {
        $request = $this->createRequest($method, $uri);

        if ($data !== null) {
            $request = $request->withParsedBody($data);
        }

        return $request->withHeader('Content-Type', 'application/x-www-form-urlencoded');
    }
}
