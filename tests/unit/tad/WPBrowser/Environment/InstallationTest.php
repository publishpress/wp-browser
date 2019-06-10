<?php namespace tad\WPBrowser\Environment;

use tad\WPBrowser\WPCLI\BufferLogger;

class InstallationTest extends \Codeception\Test\Unit
{
    /**
     * It should throw if the root directory does not exist
     *
     * @test
     */
    public function should_throw_if_the_root_directory_does_not_exist()
    {
        $this->expectException(\InvalidArgumentException::class);

        new Installation(codecept_output_dir('foo/bar'));
    }

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
        $core = $this->prophesize(\Core_Command::class);
        $logger = new BufferLogger();

        $installation = new Installation($rootDir, $version, $locale, $skipContent);
        $installation->setCoreCommand($core->reveal());
        $installation->setWpCliLogger($logger);
        $installation->download();

        $core->download([], [
            'path' => $rootDir,
            'locale' => $locale,
            'version' => $version,
            'skipContent' => $skipContent,
            'force' => false
        ])->shouldHaveBeenCalledOnce();
    }

    public function installationDownloadData()
    {
        return [
            'dir-one' => [codecept_output_dir('wp-installations/dir-one'), 'latest', 'en_US', true],
            'dir-two' => [codecept_output_dir('wp-installations/dir-two'), '3.5', 'it_IT', false],
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
                'luca@foo.com'
            ],
            'dir-two' => [
                codecept_output_dir('wp-installations/dir-two'),
                'http://example.com/foo',
                'Test Site',
                'admin',
                'foo',
                'admin@foo.bar'
            ],
        ];
    }

    /**
     * It should correctly configure and install the installation
     *
     * @test
     * @dataProvider installationInstallationData
     */
    public function should_correctly_configure_and_install_the_installation(
        $rootDir,
        $url,
        $title,
        $adminUser,
        $adminPassword,
        $adminEmail
    ) {
        $core = $this->prophesize(\Core_Command::class);
        $logger = new BufferLogger();

        $installation = new Installation($rootDir);
        $installation->setCoreCommand($core->reveal());
        $installation->setWpCliLogger($logger);

        $installation->install($url, $title, $adminUser, $adminPassword, $adminEmail);

        $core->install([], [
            'path' => $rootDir,
            'url' => $url,
            'title' => $title,
            'admin_user' => $adminUser,
            'admin_password' => $adminPassword,
            'admin_email'=>$adminEmail,
            'skip-email' => true,
        ])->shouldHaveBeenCalledOnce();
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
