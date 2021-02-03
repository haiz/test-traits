<?php

namespace Haiz\TestTrait\Http;

use InvalidArgumentException;

use function array_key_exists;
use function array_replace;
use function count;
use function explode;
use function gmdate;
use function in_array;
use function is_array;
use function is_string;
use function preg_split;
use function rtrim;
use function strtolower;
use function strtotime;
use function urldecode;
use function urlencode;

class Cookies
{
    /**
     * Cookies from HTTP request
     *
     * @var array
     */
    protected $requestCookies = [];

    /**
     * Cookies for HTTP response
     *
     * @var array
     */
    protected $responseCookies = [];

    /**
     * Default cookie properties
     *
     * @var array
     */
    protected $defaults = [
        'value' => '',
        'domain' => null,
        'hostonly' => null,
        'path' => null,
        'expires' => null,
        'secure' => false,
        'httponly' => false,
        'samesite' => null
    ];

    /**
     * @param array $cookies
     */
    public function __construct($cookies = [])
    {
        $this->requestCookies = $cookies;
    }

    /**
     * Set default cookie properties
     *
     * @param array $settings
     *
     * @return static
     */
    public function setDefaults($settings)
    {
        $this->defaults = array_replace($this->defaults, $settings);

        return $this;
    }

    /**
     * Get cookie
     *
     * @param string            $name
     * @param string|array|null $default
     * @return mixed|null
     */
    public function get($name, $default = null)
    {
        return array_key_exists($name, $this->requestCookies) ? $this->requestCookies[$name] : $default;
    }

    /**
     * Set cookie
     *
     * @param string       $name
     * @param string|array $value
     * @return static
     */
    public function set($name, $value)
    {
        if (!is_array($value)) {
            $value = ['value' => $value];
        }

        $this->responseCookies[$name] = array_replace($this->defaults, $value);

        return $this;
    }

    /**
     * Convert all response cookies into an associate array of header values
     *
     * @return array
     */
    public function toHeaders()
    {
        $headers = [];

        foreach ($this->responseCookies as $name => $properties) {
            $headers[] = $this->toHeader($name, $properties);
        }

        return $headers;
    }

    /**
     * Convert to `Set-Cookie` header
     *
     * @param  string $name       Cookie name
     * @param  array  $properties Cookie properties
     *
     * @return string
     */
    protected function toHeader($name, $properties)
    {
        $result = urlencode($name) . '=' . urlencode($properties['value']);

        if (isset($properties['domain'])) {
            $result .= '; domain=' . $properties['domain'];
        }

        if (isset($properties['path'])) {
            $result .= '; path=' . $properties['path'];
        }

        if (isset($properties['expires'])) {
            if (is_string($properties['expires'])) {
                $timestamp = strtotime($properties['expires']);
            } else {
                $timestamp = (int) $properties['expires'];
            }
            if ($timestamp && $timestamp !== 0) {
                $result .= '; expires=' . gmdate('D, d-M-Y H:i:s e', $timestamp);
            }
        }

        if (isset($properties['secure']) && $properties['secure']) {
            $result .= '; secure';
        }

        if (isset($properties['hostonly']) && $properties['hostonly']) {
            $result .= '; HostOnly';
        }

        if (isset($properties['httponly']) && $properties['httponly']) {
            $result .= '; HttpOnly';
        }

        if (isset($properties['samesite']) && in_array(strtolower($properties['samesite']), ['lax', 'strict'], true)) {
            // While strtolower is needed for correct comparison, the RFC doesn't care about case
            $result .= '; SameSite=' . $properties['samesite'];
        }

        return $result;
    }

    /**
     * Parse cookie values from header value
     *
     * Returns an associative array of cookie names and values
     *
     * @param string|array $header
     *
     * @return array
     */
    public static function parseHeader($header)
    {
        if (is_array($header)) {
            $header = isset($header[0]) ? $header[0] : '';
        }

        if (!is_string($header)) {
            throw new InvalidArgumentException('Cannot parse Cookie data. Header value must be a string.');
        }

        $header = rtrim($header, "\r\n");
        $pieces = preg_split('@[;]\s*@', $header);
        $cookies = [];

        if (is_array($pieces)) {
            foreach ($pieces as $cookie) {
                $cookie = explode('=', $cookie, 2);

                if (count($cookie) === 2) {
                    $key = urldecode($cookie[0]);
                    $value = urldecode($cookie[1]);

                    if (!isset($cookies[$key])) {
                        $cookies[$key] = $value;
                    }
                }
            }
        }

        return $cookies;
    }
}
