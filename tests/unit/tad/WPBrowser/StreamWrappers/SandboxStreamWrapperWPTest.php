<?php
/**
 * ${CARET}
 *
 * @since TBD
 *
 * @package unit\tad\WPBrowser\StreamWrappers
 */


namespace unit\tad\WPBrowser\StreamWrappers;


use Codeception\Test\Unit;
use tad\WPBrowser\StreamWrappers\SandboxStreamWrapper;

class SandboxStreamWrapperWPTest extends Unit
{
    /**
     * It should allow including wp-admin/admin-ajax.php file
     *
     * @test
     */
    public function should_allow_including_wp_admin_admin_ajax_php_file()
    {
        $file = codecept_root_dir('vendor/wordpress/wordpress/wp-admin/admin-ajax.php');

        $wrapper = new SandboxStreamWrapper();
        $wrapper->setWhitelist([codecept_root_dir('vendor/wordpress/wordpress')]);
        $run  = $wrapper->loadFile($file);
    }
}
