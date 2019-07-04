<?php

namespace tad\WPBrowser\Connector;

use Codeception\Exception\ModuleException;
use Codeception\Lib\Connector\Universal;
use Symfony\Component\BrowserKit\CookieJar;
use Symfony\Component\BrowserKit\History;
use Symfony\Component\BrowserKit\Request;
use Symfony\Component\BrowserKit\Response;
use tad\WPBrowser\Adapters\Process as Processes;
use tad\WPBrowser\Module\Support\UriToIndexMapper;
use tad\WPBrowser\Utils;

class WordPress extends Universal
{
    /**
     * @var bool
     */
    protected $insulated = true;

    /**
     * @var string
     */
    protected $url;

    /**
     * @var string
     */
    protected $domain;

    /**
     * @var array
     */
    protected $headers;

    /**
     * @var string
     */
    protected $rootFolder;
    /**
     * @var UriToIndexMapper
     */
    protected $uriToIndexMapper;

    public function __construct(
        array $server = array(),
        History $history = null,
        CookieJar $cookieJar = null,
        UriToIndexMapper $uriToIndexMapper = null
    ) {
        parent::__construct($server, $history, $cookieJar);
        $this->uriToIndexMapper = $uriToIndexMapper ? $uriToIndexMapper : new UriToIndexMapper($this->rootFolder);
    }

    /**
     * Performs the request in a separate process.
     *
     * @param Request        $request The request to perform.
     * @param Processes|null $processes Either the Process adapter or `null` to build it in the method.
     *
     * @return Response The request response.
     * @throws ModuleException If the response unserialization fails.
     */
    public function doRequestInProcess($request, Processes $processes = null)
    {
        if ($this->mockedResponse) {
            $response = $this->mockedResponse;
            $this->mockedResponse = null;
            return $response;
        }

        $processes = $processes ?: new Processes();

        $requestCookie = $request->getCookies();
        $requestServer = $request->getServer();
        $requestFiles = $this->remapFiles($request->getFiles());

        $parseResult = parse_url($request->getUri());
        $uri = $parseResult['path'];
        if (array_key_exists('query', $parseResult)) {
            $uri .= '?' . $parseResult['query'];
        }

        $requestRequestArray = $this->remapRequestParameters($request->getParameters());

        $requestServer['REQUEST_METHOD'] = strtoupper($request->getMethod());
        $requestServer['REQUEST_URI'] = $uri;
        $requestServer['HTTP_HOST'] = $this->domain;
        $requestServer['SERVER_PROTOCOL'] = 'HTTP/1.1';
        $requestServer['SERVER_NAME'] = $this->domain;
        $requestServer['HTTP_CLIENT_IP'] = '127.0.0.1';

        $this->index = $this->uriToIndexMapper->getIndexForUri($uri);

        $requestServer['PHP_SELF'] = str_replace($this->rootFolder, '', $this->index);

        $env = [
            'headers' => $this->headers,
            'cookie' => $requestCookie,
            'server' => $requestServer,
            'files' => $requestFiles,
            'request' => $requestRequestArray,
        ];

        $requestMethod = $request->getMethod() === 'GET' ? 'get' : 'post';
        $env[$requestMethod] = $env['request'];

        $requestScript = dirname(dirname(__DIR__)) . '/scripts/request.php';

        $command = PHP_BINARY .
            ' ' . escapeshellarg($requestScript) .
            ' ' . escapeshellarg($this->index) .
            ' ' . escapeshellarg(base64_encode(serialize($env)));

        $process = $processes->forCommand($command);
        $process->run();
        $rawProcessOutput = $process->getOutput();

        /** @noinspection UnserializeExploitsInspection */
        $unserializedResponse = @unserialize(base64_decode($rawProcessOutput));

        if (false === $unserializedResponse) {
            $message = 'Server responded with: ' . $rawProcessOutput;
            throw new ModuleException(\Codeception\Module\WordPress::class, $message);
        }

        foreach (['server', 'files', 'request', 'get', 'post'] as $key) {
            if (empty($unserializedResponse[$key])) {
                continue;
            }
            $superGlobal = '_' . strtoupper($key);
            $GLOBALS[$superGlobal] = isset($GLOBALS[$superGlobal]) ?
                /** @noinspection SlowArrayOperationsInLoopInspection */
                array_merge($GLOBALS[$superGlobal], $unserializedResponse[$key]) : $unserializedResponse[$key];
        }

        $content = $unserializedResponse['content'];
        $headers = Utils\Str::replaceRecursive($this->url, '', $unserializedResponse['headers']);

        $response = new Response($content, $unserializedResponse['status'], $headers);

        return $response;
    }

    public function setUrl($url)
    {
        $this->url = $url;
    }

    public function setIndexFor($uri)
    {
        $this->index = $this->rootFolder . $this->uriToIndexMapper->getIndexForUri($uri);
    }

    public function getIndex()
    {
        return $this->index;
    }

    public function getRootFolder()
    {
        return $this->rootFolder;
    }

    /**
     * @param string $rootFolder
     */
    public function setRootFolder($rootFolder)
    {
        if (!is_dir($rootFolder)) {
            throw new \InvalidArgumentException('Root folder [' . $rootFolder . '] is not an existing folder!');
        }
        $this->rootFolder = $rootFolder;
        $this->uriToIndexMapper->setRoot($rootFolder);
    }

    public function getHeaders()
    {
        return $this->headers;
    }

    public function setHeaders(array $headers = [])
    {
        $this->headers = $headers;
    }

    public function getDomain()
    {
        return $this->domain;
    }

    public function setDomain($domain)
    {
        $this->domain = $domain;
    }

    public function resetCookies()
    {
        $this->cookieJar = new CookieJar();
    }
}
