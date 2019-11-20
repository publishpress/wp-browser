<?php namespace tad\WPBrowser\Installation;

class InstallationTest extends \Codeception\Test\Unit
{
    /**
     * It should build th correct type of installation from paths
     *
     * @test
     */
    public function should_build_th_correct_type_of_installation_from_paths()
    {
        $this->assertInstanceOf(
            Installation::class,
            Installation::fromDir(codecept_data_dir('folder-structures/wp-struct-2'))
        );
        $this->assertInstanceOf(
            NotFoundInstallation::class,
            Installation::fromDir(codecept_data_dir())
        );
    }

    /**
     * It should throw when building installation on non wp root
     *
     * @test
     */
    public function should_throw_when_building_installation_on_non_wp_root()
    {
        $this->expectException(InstallationException::class);

        new Installation(codecept_data_dir());

        $this->expectException(InstallationException::class);

        new Installation(codecept_data_dir('folder-structures/folder-1'));
    }

    /**
     * It should throw if wp-config.php file cannot be found in root dir or above
     *
     * @test
     */
    public function should_throw_if_wp_config_php_file_cannot_be_found_in_root_dir_or_above()
    {
        $this->expectException(InstallationException::class);

        new Installation(codecept_data_dir('folder-structures/wp-struct-1/wp'));
    }

    /**
     * It should expose installation paths
     *
     * @test
     */
    public function should_expose_installation_paths()
    {
        $wpRootDir = codecept_data_dir('folder-structures/wp-struct-2');
        $i = Installation::fromDir($wpRootDir);

        $this->assertEquals($wpRootDir . '/wp-config.php', $i->getWpConfigFile());
        $this->assertEquals($wpRootDir . '/foo/bar', $i->getDir('/foo/bar'));
        $this->assertEquals($wpRootDir . '/wp-config.php', $i->getDir('wp-config.php'));
    }

    /**
     * It should throw when building on parent w/ config
     *
     * @test
     */
    public function should_throw_when_building_on_parent_w_config()
    {
        $wpRootDir = codecept_data_dir('folder-structures/wp-w-config-in-parent');

        $this->expectException(InstallationException::class);

        new  Installation($wpRootDir);
    }

    /**
     * It should correctly build on dir w/ config in parent
     *
     * @test
     */
    public function should_correctly_build_on_dir_w_config_in_parent()
    {
        $wpRootDir = codecept_data_dir('folder-structures/wp-w-config-in-parent/wp');
        $i = Installation::fromDir($wpRootDir);

        $this->assertEquals(realpath($wpRootDir . '/../wp-config.php'), $i->getWpConfigFile());
        $this->assertEquals($wpRootDir . '/foo/bar', $i->getDir('/foo/bar'));
        $this->assertEquals(realpath($wpRootDir . '/../wp-config.php'), $i->getDir('wp-config.php'));
    }

    /**
     * It should correctly return the wp-admin path on std install structure
     *
     * @test
     */
    public function should_correctly_return_the_wp_admin_path_on_std_install_structure()
    {
        $wpRootDir = codecept_data_dir('folder-structures/wp-struct-2');
        $i = Installation::fromDir($wpRootDir);

        $this->assertEquals(realpath($wpRootDir . '/wp-admin') . '/', $i->getWpAdminDir());
        $this->assertEquals(realpath($wpRootDir . '/wp-admin') . '/', $i->getDir('wp-admin'));
    }

    /**
     * It should correctly return the wp-admin path on nested wp dir installation
     *
     * @test
     */
    public function should_correctly_return_the_wp_admin_path_on_nested_wp_dir_installation()
    {
        $wpRootDir = codecept_data_dir('folder-structures/wp-w-config-in-parent/wp');
        $i = Installation::fromDir($wpRootDir);

        $this->assertEquals(realpath($wpRootDir . '/wp-admin') . '/', $i->getWpAdminDir());
        $this->assertEquals(realpath($wpRootDir . '/wp-admin') . '/', $i->getDir('wp-admin'));
    }

    /**
     * It should allow building on the index folder of installation moved to subdir
     *
     * @test
     */
    public function should_allow_building_on_the_index_folder_of_installation_moved_to_subdir()
    {
        $indexDir = codecept_data_dir('folder-structures/wp-in-subdir');
        $i = Installation::fromDir($indexDir);

        $this->assertInstanceOf(Installation::class, $i);
        $this->assertEquals(realpath($indexDir . '/wp') . '/', $i->getDir());
        $this->assertEquals(realpath($indexDir . '/wp/wp-admin') . '/', $i->getWpAdminDir());
    }
}
