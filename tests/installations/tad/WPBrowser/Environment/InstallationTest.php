<?php namespace tad\WPBrowser\Environment;

use org\bovigo\vfs\vfsStream;
use Symfony\Component\Process\Process;
use tad\WPBrowser\Exceptions\InstallationException;
use tad\WPBrowser\Interfaces\WordPressDatabaseInterface;
use tad\WPBrowser\Traits\WithSqliteDatabase;
use tad\WPBrowser\Traits\WithWpCli;

class InstallationTest extends \Codeception\Test\Unit
{
    use WithWpCli;
    use WithSqliteDatabase;

    /**
     * It should allow creating an installation from a writeable dir
     *
     * @test
     */
    public function should_allow_creating_an_installation_from_a_writeable_dir()
    {
        $rootDir = codecept_output_dir();
        new Installation($rootDir);
    }

    /**
     * It should return the root dir with paths
     *
     * @test
     * 2
     * @dataProvider rootDirPaths
     */
    public function should_return_the_root_dir_with_paths($path, $expected)
    {
        $installation = new Installation(codecept_output_dir());

        $path = $installation->getRootDir($path);

        $this->assertEquals($expected, $path);
    }

    public function rootDirPaths()
    {
        return [
            'null' => [null, rtrim(codecept_output_dir(), '/')],
            'empty_string' => ['', rtrim(codecept_output_dir(), '/')],
            'w_leading_slash' => ['/foo', codecept_output_dir('foo')],
            'wo_leading_slash' => ['foo', codecept_output_dir('foo')],
            'w_trailing_slash' => ['foo', rtrim(codecept_output_dir('foo'), '/')],
            'w_slash' => ['/foo/', rtrim(codecept_output_dir('foo'), '/')],
            'multi_w_leading_slash' => ['/foo/bar', codecept_output_dir('foo/bar')],
            'multi_wo_leading_slash' => ['foo/bar', codecept_output_dir('foo/bar')],
            'multi_w_trailing_slash' => ['foo/bar', rtrim(codecept_output_dir('foo/bar'), '/')],
            'multi_w_slash' => ['/foo/bar/', rtrim(codecept_output_dir('foo/bar'), '/')],
        ];
    }

    /**
     * It should download the specified WordPress version on download
     *
     * @test
     * @dataProvider installationDownloadData
     */
    public function should_download_the_specified_word_press_version_on_download(
        $rootDir,
        $version,
        $locale,
        $skipContent
    ) {
        rrmdir($rootDir);
        $installation = new Installation($rootDir, $version, $locale, $skipContent);
        $installation->download();

        $this->assertFileExists($rootDir . '/wp-load.php');
        $version = trim($this->setUpWpCli($rootDir)->executeWpCliCommand(['core', 'version'])->getOutput(), PHP_EOL);
        $this->assertEquals($version, $installation->getVersion());
    }

    /**
     * It should throw if download process fails
     *
     * @test
     */
    public function should_throw_if_download_process_fails()
    {
        $rootDir = codecept_output_dir('wp-installations/' . $this->getTestName());
        $installation = new Installation($rootDir, 'not-existing-version');

        $this->expectException(InstallationException::class);

        $installation->download();
    }

    /**
     * It should throw if root dir is not writeable
     *
     * @test
     */
    public function should_throw_if_root_dir_is_not_writeable()
    {
        $rootDir = vfsStream::setup('root');
        $rootDir->addChild(vfsStream::newDirectory('wp', 0000));
        $installation = new Installation($rootDir->url() . '/wp');

        $this->expectException(InstallationException::class);

        $installation->download();
    }

    public function installationDownloadData()
    {
        return [
            'dir-one' => [codecept_output_dir('wp-installations/dir-one'), 'latest', 'en_US', true],
            'dir-two' => [codecept_output_dir('wp-installations/dir-two'), '5.0', 'it_IT', false],
        ];
    }

    public function installationInstallationData()
    {
        return [
            'dir-one' => [
                codecept_output_dir('wp-installations/dir-one'),
                'http://foo.bar',
                'Test 1',
                'luca',
                'bar',
                'luca@foo.com',
                8888
            ],
            'dir-two' => [
                codecept_output_dir('wp-installations/dir-two'),
                'http://example.com/foo',
                'Test Site',
                'admin',
                'foo',
                'admin@foo.bar',
                8889
            ],
        ];
    }

    /**
     * It should correctly configure and install the installation
     *
     * @test
     * @dataProvider installationInstallationData
     * @depends      should_download_the_specified_word_press_version_on_download
     */
    public function should_correctly_configure_and_install_the_installation(
        $rootDir,
        $url,
        $title,
        $adminUser,
        $adminPassword,
        $adminEmail
    ) {
        $installation = new Installation($rootDir);
        $db = $this->createSqliteDatabase('database', $rootDir)->setInstallation($installation);

        $installation->configure($db);

        $this->assertTrue($installation->isConfigured());

        $installation->install($url, $title, $adminUser, $adminPassword, $adminEmail);

        $this->assertTrue($installation->isInstalled());
        $expectedContentDirPath = $rootDir . '/wp-content';
        $this->assertEquals($expectedContentDirPath, $installation->getWpContentDir());
        $this->assertSame($db, $installation->getDatabase());
    }

    /**
     * It should throw if configuration process fails
     *
     * @test
     */
    public function should_throw_if_configuration_process_fails()
    {
        $rootDir = vfsStream::setup('root');
        $rootDir->addChild(vfsStream::newDirectory('wp'));
        $rootDirPath = $rootDir->url() . '/wp';
        $installation = new Installation($rootDirPath);
        $db = $this->makeEmpty(WordPressDatabaseInterface::class);

        $this->expectException(InstallationException::class);

        $installation->configure($db);
    }

    /**
     * It should throw if installation process fails
     *
     * @test
     */
    public function should_throw_if_installation_process_fails()
    {
        $rootDir = vfsStream::setup('root');
        $rootDir->addChild(vfsStream::newDirectory('wp'));
        $rootDirPath = $rootDir->url() . '/wp';
        $installation = new Installation($rootDirPath);

        $this->expectException(InstallationException::class);

        $installation->install();
    }

    /**
     * It should allow serving an installation on localhost
     *
     * @test
     * @depends      should_correctly_configure_and_install_the_installation
     * @dataProvider installationInstallationData
     */
    public function should_allow_serving_an_installation_on_localhost(
        $rootDir,
        $url,
        $title,
        $adminUser,
        $adminPassword,
        $adminEmail,
        $port
    ) {
        $installation = new Installation($rootDir);

        if ($rootDir === codecept_output_dir('wp-installations/dir-one')) {
            // Make sure the installation has a theme.
            $themeInstall = $installation->cli(['theme', 'install', 'twentyseventeen', '--activate']);
            if ($themeInstall->getErrorOutput()) {
                throw new \RuntimeException(
                    'Failed installing twentyseventeen theme: '
                    . $themeInstall->getErrorOutput()
                );
            }
        }

        $this->assertFalse($installation->getServerPort());
        $this->assertFalse($installation->getServerUrl());
        $this->assertFalse($installation->isBeingServed());

        $url = $installation->serve($port);

        $this->assertEquals($port, $installation->getServerPort());
        $this->assertEquals("http://localhost:{$port}", $url);
        $this->assertEquals("http://localhost:{$port}", $installation->getServerUrl());
        $this->assertTrue($installation->isBeingServed());
        $response = $this->requestUrl($installation);
        $this->assertEquals(200, $response['http_code']);
        $this->assertEquals($port, $response['primary_port']);

        $this->expectException(InstallationException::class);
        $installation->serve($port);

        $installation->stopServing();

        $this->assertFalse($installation->isBeingServed());
        $this->assertFalse($installation->getServerPort());
        $this->assertFalse($installation->getServerUrl());
        $response = $this->requestUrl($installation);
        $this->assertEquals(0, $response['http_code']);
    }

    protected function requestUrl(Installation $installation)
    {
        $request = curl_init($installation->getServerUrl());
        curl_setopt($request, CURLOPT_RETURNTRANSFER, true);
        $content = curl_exec($request);
        $response = curl_getinfo($request);
        $response['content'] = $content;

        curl_close($request);

        return $response;
    }

    /**
     * It should allow serving the installation on a random port
     *
     * @test
     * @depends      should_correctly_configure_and_install_the_installation
     */
    public function should_allow_serving_the_installation_on_a_random_port()
    {
        $rootDir = codecept_output_dir('wp-installations/dir-two');
        $installation = new Installation($rootDir);

        $this->assertFalse($installation->getServerPort());
        $this->assertFalse($installation->getServerUrl());
        $this->assertFalse($installation->isBeingServed());

        $url = $installation->serve();
        // Serve it again to test this will not fail.
        $installation->serve();

        $port = $installation->getServerPort();
        $this->assertNotEmpty($port);
        $this->assertEquals("http://localhost:{$port}", $url);
        $this->assertEquals("http://localhost:{$port}", $installation->getServerUrl());
        $this->assertTrue($installation->isBeingServed());
        $response = $this->requestUrl($installation);
        $this->assertEquals(200, $response['http_code']);
        $this->assertEquals($port, $response['primary_port']);

        $installation->stopServing();

        $this->assertFalse($installation->isBeingServed());
        $this->assertFalse($installation->getServerPort());
        $this->assertFalse($installation->getServerUrl());
        $response = $this->requestUrl($installation);
        $this->assertEquals(0, $response['http_code']);
    }

    /**
     * It should throw if stop server process fails
     *
     * @test
     */
    public function should_throw_if_stop_server_process_fails()
    {
        $rootDir = vfsStream::setup('root');
        $rootDir->addChild(vfsStream::newDirectory('wp'));
        $rootDirPath = $rootDir->url() . '/wp';
        $installation = new Installation($rootDirPath);

        $throw = static function () {
            throw new \RuntimeException('Error');
        };

        $serverProcess = $this->make(Process::class, [
            'isRunning' => true,
            'getPid' => 23,
            'stop' => $throw
        ]);

        $this->expectException(InstallationException::class);

        $installation->attachServerProcess($serverProcess, 9089);
        $installation->stopServing();
    }

    /**
     * It should throw if server process does not terminate
     *
     * @test
     */
    public function should_throw_if_server_process_does_not_terminate()
    {
        $rootDir = vfsStream::setup('root');
        $rootDir->addChild(vfsStream::newDirectory('wp'));
        $rootDirPath = $rootDir->url() . '/wp';
        $installation = new Installation($rootDirPath);

        $serverProcess = $this->make(Process::class, [
            'isRunning' => true,
            'stop' => true,
            'getPid' => 23,
            'getStatus' => Process::STATUS_STARTED
        ]);

        $this->expectException(InstallationException::class);

        $installation->attachServerProcess($serverProcess, 9089);
        $installation->stopServing();
    }

    /**
     * It should throw if serve process fails
     *
     * @test
     */
    public function should_throw_if_serve_process_fails()
    {
        $rootDir = vfsStream::setup('root');
        $rootDir->addChild(vfsStream::newDirectory('wp'));
        $rootDirPath = $rootDir->url() . '/wp';
        $installation = new Installation($rootDirPath);
        $db = $this->makeEmpty(WordPressDatabaseInterface::class);

        $this->expectException(InstallationException::class);

        $installation->serve();
    }

    protected function _before()
    {
        foreach ([
                     codecept_output_dir('wp-installations/dir-one'),
                     codecept_output_dir('wp-installations/dir-two'),
                 ] as $rootDir) {
            if (!is_dir($rootDir)) {
                mkdir($rootDir, 0777, true);
            }
        }
    }
}
