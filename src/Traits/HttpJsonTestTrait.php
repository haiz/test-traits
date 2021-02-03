<?php

namespace Haiz\TestTrait\Traits;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;

/**
 * HTTP JSON Test Trait.
 */
trait HttpJsonTestTrait
{
    use HttpTestTrait;
    use ArrayTestTrait;

    /**
     * Create a JSON request.
     *
     * @param string $method The HTTP method
     * @param string|UriInterface $uri The URI
     * @param array<mixed>|null $data The json data
     *
     * @return ServerRequestInterface
     */
    protected function createJsonRequest($method, $uri, $data = null)
    {
        $request = $this->createRequest($method, $uri);

        if ($data !== null) {
            $request = $request->withParsedBody($data);
        }

        return $request->withHeader('Content-Type', 'application/json');
    }

    /**
     * Get JSON response as array.
     *
     * @param ResponseInterface $response
     *
     * @return array The data
     */
    protected function getJsonData($response)
    {
        $actual = (string)$response->getBody();
        $this->assertJson($actual);

        return (array)json_decode($actual, true);
    }

    /**
     * Verify that the specified array is an exact match for the returned JSON.
     *
     * @param array<mixed> $expected The expected array
     * @param ResponseInterface $response The response
     *
     * @return void
     */
    protected function assertJsonData($expected, $response)
    {
        $this->assertSame($expected, $this->getJsonData($response));
    }

    /**
     * Verify JSON response.
     *
     * @param ResponseInterface $response The response
     *
     * @return void
     */
    protected function assertJsonContentType($response)
    {
        $this->assertStringContainsString('application/json', $response->getHeaderLine('Content-Type'));
    }

    /**
     * Verify that the specified array is an exact match for the returned JSON.
     *
     * @param mixed $expected The expected value
     * @param string $path The array path
     * @param ResponseInterface $response The response
     *
     * @return void
     */
    protected function assertJsonValue($expected, $path, $response)
    {
        $this->assertSame($expected, $this->getArrayValue($this->getJsonData($response), $path));
    }
}
