<?php

namespace tad\WPBrowser;

use Codeception\Test\Unit;

class wpTest extends Unit
{
    public function findWordPressRootDirDataSet()
    {
        yield 'same' => [
            codecept_data_dir('folder-structures/wp-struct-1/wp'),
            codecept_data_dir('folder-structures/wp-struct-1/wp')
        ];

        yield 'immediate_parent' => [
            codecept_data_dir('folder-structures/wp-struct-1/wp/wp-content'),
            codecept_data_dir('folder-structures/wp-struct-1/wp')
        ];

        yield 'removed_parent' => [
            codecept_data_dir('folder-structures/wp-struct-1/wp/wp-content/plugins/test-plugin'),
            codecept_data_dir('folder-structures/wp-struct-1/wp')
        ];

        yield 'same' => [
            codecept_data_dir('folder-structures/wp-struct-1/wp'),
            codecept_data_dir('folder-structures/wp-struct-1/wp')
        ];

        yield 'immediate_child' => [
            codecept_data_dir('folder-structures/wp-struct-1'),
            codecept_data_dir('folder-structures/wp-struct-1/wp')
        ];

        yield 'removed_child' => [
            codecept_data_dir('folder-structures'),
            codecept_data_dir('folder-structures/wp-struct-1/wp')
        ];

        yield 'not_available' => [
            __DIR__,
            getcwd()
        ];
    }

    /**
     * @dataProvider findWordPressRootDirDataSet
     */
    public function test_findWordPressRootDir($input, $expected)
    {
        $this->assertEquals($expected, findWordPressRootDir($input));
    }

    public function test_getWpConfigArgs()
    {
        $wpConfigArgs = getWpConfigArgs(codecept_root_dir('vendor/wordpress/wordpress'));
        $this->assertNotFalse($wpConfigArgs);
        $this->assertIsArray($wpConfigArgs);
        $this->assertArrayHasKey('constants', $wpConfigArgs);
        $this->assertArrayHasKey('vars', $wpConfigArgs);
    }

    public function findDbCoordinatesDataSet()
    {
        return [
            'no_wp_config' => [__DIR__, false],
            'missing_db_host' => [
                codecept_data_dir('folder-structures/wp-struct-2'),
                false
            ],
            'w_test_db_host_and_test_settings' => [
            codecept_data_dir('folder-structures/wp-struct-3'),
                [
                    'DB_HOST' => '127.0.0.1:3307',
                    'DB_NAME' => 'test_site',
                    'DB_USER' => 'root',
                    'DB_PASSWORD' => '',
                    'table_prefix' => 'wp_'
                ]
            ]
        ];
    }

    /**
     * @dataProvider findDbCoordinatesDataSet
     */
    public function testFindDbCoordinates($dir, $expected)
    {
        $this->assertEquals($expected, findWpDbCreds($dir));
    }
}
