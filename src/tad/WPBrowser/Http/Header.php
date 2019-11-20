<?php
/**
 * A value object representing an HTTP header.
 *
 * @package tad\WPBrowser\Http
 */


namespace tad\WPBrowser\Http;

/**
 * Class Header
 *
 * @package tad\WPBrowser\Http
 */
class Header
{
    /**
     * The header line, in the format `<Key>: <Value`.
     *
     * @var string
     */
    protected $value;

    /**
     * Whether this header replaces an existing one or not.
     *
     * @var bool
     */
    protected $replace;

    /**
     *  The HTTP response code for this header.
     *
     * @var int|null
     */
    protected $httpResponseCode;

    /**
     * Header constructor.
     *
     * @param string $value The raw header value.
     * @param bool $replace Whether this header replaces a previous one or not.
     * @param null $http_response_code The HTTP response code for the header; only set if the `$value` is not empty.
     */
    public function __construct($value, $replace = true, $http_response_code = null)
    {
        $frags = explode(':', $value, 2);
        $this->name = trim(reset($frags));
        $this->value = trim(end($frags));
        $this->replace = $replace;
        $this->httpResponseCode = !empty($value) ? $http_response_code : null;
    }

    /**
     * Builds the correct header depending on the value.
     *
     * @param string $value The raw header value.
     * @param bool $replace Whether this header replaces a previous one or not.
     * @param null $httpResponseCode The HTTP response code for the header; only set if the `$value` is not empty.
     * @param null $defaultResponseCode The default HTTP response code, ignored if `$httpResponseCode` is set.
     *
     * @return static The correct instance of the class.
     */
    public static function make($value, $replace, $httpResponseCode, $defaultResponseCode = null)
    {
        if (stripos($value, 'HTTP/') === 0) {
            return new HttpHeader($value, $replace, $httpResponseCode);
        }

        if (stripos($value, 'Location:') === 0) {
            return new LocationHeader($value, $replace, $httpResponseCode ?: $defaultResponseCode);
        }

        return new static($value, $replace, $httpResponseCode);
    }

    /**
     * Returns the header name, the part before the first `:`.
     *
     * @return string The header name, the part before the first `:`.
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Returns the header value, after the first `:`.
     *
     * @return string The header value, after the first `:`.
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * Returns the header HTTP response code, if any.
     *
     * @return int|null The header HTTP response code, if any.
     */
    public function getResponseCode()
    {
        return $this->httpResponseCode;
    }
}
