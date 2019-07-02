<?php namespace tad\WPBrowser\Environment;

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
     *
     * @param $rootDir
     * @param $version
     * @param $locale
     * @param $skipContent
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
        curl_exec($request);
        $response = curl_getinfo($request);
        curl_close($request);

        return $response;
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
