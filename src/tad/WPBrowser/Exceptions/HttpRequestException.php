<?php
/**
 * An exception thrown while making, or preparing, an HTTP request.
 *
 * @package tad\WPBrowser\Exceptions
 */

namespace tad\WPBrowser\Exceptions;

/**
 * Class HttpRequestException
 *
 * @package tad\WPBrowser\Exceptions
 */
class HttpRequestException extends \Exception
{

    /**
     * Builds and returns an exception to indicate the root URL was not set before making a request.
     *
     * @param string $method The method name.
     *
     * @return HttpRequestException The built exception.
     */
    public static function becauseRootUrlIsNotSet($method)
    {
        return new static("The`WithHTTPRequests::{$method}` method requires setting a root URL first; " .
            'set the URL callling the `WithHTTPRequests::setHttpRequestsRootUrl` method first.');
    }
}
