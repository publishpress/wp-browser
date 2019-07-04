<?php

namespace tad\WPBrowser\Module\Connector;

use Codeception\Exception\ModuleException;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use Prophecy\Argument;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\BrowserKit\CookieJar;
use Symfony\Component\BrowserKit\History;
use Symfony\Component\BrowserKit\Request;
use Symfony\Component\BrowserKit\Response;
use tad\WPBrowser\Adapters\Process;
use tad\WPBrowser\Connector\WordPress;
use tad\WPBrowser\Module\Support\UriToIndexMapper;

class WordPressTest extends \Codeception\Test\Unit
{
    protected $backupGlobals = false;
    /**
     * @var \UnitTester
     */
    protected $tester;

    /**
     * @var array
     */
    protected $server = [];

    /**
     * @var History
     */
    protected $history;

    /**
     * @var CookieJar
     */
    protected $cookieJar;

    /**
     * @var UriToIndexMapper
     */
    protected $uriToIndexMapper;

    /**
     * @var vfsStreamDirectory
     */
    protected $root;

    /**
     * @test
     * it should be instantiatable
     */
    public function it_should_be_instantiatable()
    {
        $sut = $this->make_instance();

        $this->assertInstanceOf(WordPress::class, $sut);
    }

    /**
     * @return WordPress
     */
    private function make_instance()
    {
        return new WordPress(
            $this->server,
            $this->history->reveal(),
            $this->cookieJar->reveal(),
            $this->uriToIndexMapper->reveal()
        );
    }

    /**
     * @test
     * it should allow setting the url
     */
    public function it_should_allow_setting_the_url()
    {
        $sut = $this->make_instance();

        $sut->setDomain('http://some-url.dev');

        $this->assertEquals('http://some-url.dev', $sut->getDomain());
    }

    /**
     * @test
     * it should allow setting the domain
     */
    public function it_should_allow_setting_the_domain()
    {
        $sut = $this->make_instance();

        $sut->setDomain('some-domain.dev');


        $this->assertEquals('some-domain.dev', $sut->getDomain());
    }

    /**
     * @test
     * it should allow setting the headers
     */
    public function it_should_allow_setting_the_headers()
    {
        $sut = $this->make_instance();

        $headers = ['foo' => 'bar', 'baz' => 'foo'];
        $sut->setHeaders($headers);

        $this->assertEquals($headers, $sut->getHeaders());
    }

    /**
     * @test
     * it should allow setting the root folder
     */
    public function it_should_allow_setting_the_root_folder()
    {
        $sut = $this->make_instance();

        $sut->setRootFolder(__DIR__);

        $this->assertEquals(__DIR__, $sut->getRootFolder());
    }

    /**
     * @test
     * it should throw if set root folder does not exist
     */
    public function it_should_throw_if_set_root_folder_does_not_exist()
    {
        $sut = $this->make_instance();

        $this->expectException(\InvalidArgumentException::class);

        $sut->setRootFolder('some-folder');
    }

    /**
     * @test
     * it should throw if set root folder is not folder
     */
    public function it_should_throw_if_set_root_folder_is_not_folder()
    {
        $sut = $this->make_instance();

        $this->expectException(\InvalidArgumentException::class);

        $sut->setRootFolder(__FILE__);
    }

    /**
     * @test
     * it should set index with uri to index map when setting index for uri
     */
    public function it_should_set_index_with_uri_to_index_map_when_setting_index_for_uri()
    {
        $uri = '/foo';
        $this->uriToIndexMapper->setRoot($this->root->url())->shouldBeCalled();
        $this->uriToIndexMapper->getIndexForUri($uri)->willReturn('/some-index.php');

        $sut = $this->make_instance();
        $sut->setRootFolder($this->root->url());
        $sut->setIndexFor($uri);

        $this->assertEquals($this->root->url() . '/some-index.php', $sut->getIndex());
    }

    /**
     * It should return the mock response if set
     *
     * @test
     */
    public function should_return_the_mock_response_if_set()
    {
        $request = $this->prophesize(Request::class);
        $request->getCookies()->shouldNotBeCalled();
        $process = $this->prophesize(Process::class);
        $process->forCommand(Argument::any())->shouldNotBeCalled();

        $connector = $this->make_instance();
        $connector->mockResponse('foo');
        $response = $connector->doRequestInProcess($request->reveal(), $process->reveal());

        $this->assertEquals('foo', $response);
    }

    /**
     * It should correctly process GET request
     *
     * @test
     */
    public function should_correctly_process_get_request()
    {
        $this->uriToIndexMapper->getIndexForUri('/test/?foo=1')->willReturn('/test/?foo=1');
        $request = new Request('/test/?foo=1', 'GET', ['foo' => 'bar']);
        $processes = $this->prophesize(Process::class);
        $process = $this->prophesize(\Symfony\Component\Process\Process::class);
        $process->run()->willReturn(0);
        $process->getOutput()->willReturn(base64_encode(serialize([
            'content' => 'test test test',
            'headers' => ['ContentType' => 'application/json', 'Host' => 'https://foo.test/path'],
            'server' => ['lorem' => 'dolor'],
            'status' => 200
        ])));
        $scriptFile = codecept_root_dir('src/tad/scripts/request.php');
        // To force a reset during the request.
        unset($_FILES);

        $processes->forCommand(Argument::allOf(
            Argument::containingString('/test'),
            Argument::containingString($scriptFile)
        ))->willReturn($process->reveal());

        $connector = $this->make_instance();
        $connector->setUrl('https://foo.test');
        $response = $connector->doRequestInProcess($request, $processes->reveal());

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals('test test test', $response->getContent());
        $this->assertEquals(['ContentType' => 'application/json', 'Host' => '/path'], $response->getHeaders());
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('dolor', $_SERVER['lorem']);
    }

    /**
     * It should throw a module exception if response is empty
     *
     * @test
     */
    public function should_throw_a_module_exception_if_response_is_empty()
    {
        $this->uriToIndexMapper->getIndexForUri('/test')->willReturn('/test');
        $request = new Request('/test', 'GET', ['foo' => 'bar']);
        $processes = $this->prophesize(Process::class);
        $process = $this->prophesize(\Symfony\Component\Process\Process::class);
        $process->run()->willReturn(0);
        $process->getOutput()->willReturn(null);
        $scriptFile = codecept_root_dir('src/tad/scripts/request.php');
        $processes->forCommand(Argument::allOf(
            Argument::containingString('/test'),
            Argument::containingString($scriptFile)
        ))->willReturn($process->reveal());

        $this->expectException(ModuleException::class);

        $connector = $this->make_instance();
        $connector->doRequestInProcess($request, $processes->reveal());
    }

    /**
     * It should allow resetting cookies
     *
     * @test
     */
    public function should_allow_resetting_cookies()
    {
        $cookieJar = new CookieJar();
        $cookieJar->set(Cookie::fromString('One=23'));
        $connector = new WordPress([], null, $cookieJar);

        $this->assertSame($cookieJar, $connector->getCookieJar());

        $connector->resetCookies();

        $this->assertNotSame($cookieJar, $connector->getCookieJar());
        $this->assertInstanceOf(CookieJar::class, $connector->getCookieJar());
    }

    protected function _before()
    {
        $this->server = [];
        $this->history = $this->prophesize(History::class);
        $this->cookieJar = $this->prophesize(CookieJar::class);
        $this->uriToIndexMapper = $this->prophesize(UriToIndexMapper::class);

        $this->root = vfsStream::setup();
    }
}
