<?php
/**
 * A special version of an header, the HTTP one.
 *
 * @package tad\WPBrowser\Http
 */

namespace tad\WPBrowser\Http;

/**
 * Class HttpHeader
 *
 * @package tad\WPBrowser\Http
 */
class HttpHeader extends Header
{
    /**
     * The HTTP protocol, e.g. `HTTP/1.1`.
     *
     * @var string
     */
    protected $protocol;

    /**
     * HttpHeader constructor.
     *
     * @inheritDoc
     */
    public function __construct($value, $replace, $httpResponseCode)
    {
        preg_match('/(?<name>HTTP\\/)[^\\s]]\\s(?<code>\\d+)\\s(?<value>.*)$/', $value, $matches);

        $value = isset($matches['value']) ? $matches['value'] : null;
        $code = isset($matches['code']) ? $matches['code'] : null;

        parent::__construct('HTTP: ' . $value, $replace, $code);
        $this->protocol = isset($matches['name']) ? $matches['name'] : null;
    }
}
