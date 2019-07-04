<?php
/**
 * Provides methods to make HTTP requests from a root URL
 *
 * @package tad\WPBrowser\Traits
 */

namespace tad\WPBrowser\Traits;

use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Request;
use tad\WPBrowser\Exceptions\HttpRequestException;

/**
 * Trait WithHTTPRequests
 *
 * @package tad\WPBrowser\Traits
 */
trait WithHTTPRequests
{
    /**
     * The root URL that will be used to build all HTTP requests.
     *
     * @var string
     */
    protected $httpRequestsRootUrl;

    /**
     * Returns the root URL that is used to build all HTTP requests.
     *
     * @return string the Root URL that is used to build all HTTP requests.
     */
    protected function getHttpRequestsRootUrl()
    {
        return $this->httpRequestsRootUrl;
    }

    /**
     * Sets the root URL that will be used to build all HTTP requests.
     *
     * @param string $url the root URL that will be used to build all HTTP requests.
     */
    protected function setHttpRequestsRootUrl($url)
    {
        $this->httpRequestsRootUrl = rtrim($url, '/');
    }

    /**
     * Requests the HEAD to a page.
     *
     * @param string $page The path, relative to the root URL, to the page.
     *
     * @return PromiseInterface The HEAD request promise object.
     * @throws HttpRequestException If specifying the page as a relative path but the root URL is not set.
     */
    protected function requestHead($page = '/')
    {
        $requestUrl = $page;

        if (strpos($page, '://') === false) {
            $this->ensureHttpRequestsRootUrl('requestHead');
            $requestUrl = $this->httpRequestsRootUrl . '/' . ltrim($page, '/');
        }

        $request = new Request('HEAD', $requestUrl);
        $curl = new CurlHandler();

        return $curl($request, []);
    }

    /**
     * Checks that the root URL is set
     *
     * @param string $method The method name.
     *
     * @throws HttpRequestException If the root URL is not set.
     */
    protected function ensureHttpRequestsRootUrl($method)
    {
        if ($this->httpRequestsRootUrl === null) {
            throw HttpRequestException::becauseRootUrlIsNotSet($method);
        }
    }
}
