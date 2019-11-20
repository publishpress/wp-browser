<?php
namespace tad\WPBrowser\StreamWrappers;

use Codeception\Test\Unit;

class IncludedFilesStreamWrapperTest extends Unit
{
    /**
     * It should allow getting the files included by a file
     *
     * @test
     * @throws StreamWrapperException
     */
    public function should_allow_getting_the_files_included_by_a_file()
    {
        $file = codecept_data_dir('wrap/all_inclusion_types.php');

        $wrapper = new IncludedFilesStreamWrapper;
        $includedFiles = $wrapper->getIncludedFiles($file);

        $this->assertEquals([
            codecept_data_dir('wrap/file_1.php'),
            codecept_data_dir('wrap/file_2.php'),
            codecept_data_dir('wrap/file_3.php'),
            codecept_data_dir('wrap/file_4.php'),
            codecept_data_dir('wrap/dir/file_1.php'),
            codecept_data_dir('wrap/dir/file_2.php'),
            codecept_data_dir('wrap/dir/file_3.php'),
            codecept_data_dir('wrap/dir/file_4.php'),
        ], $includedFiles);
    }

    /**
     * It should not include not dynamically included files.
     *
     * @test
     */
    public function should_not_include_not_dynamically_included_files_()
    {
        $file = codecept_data_dir('wrap/file_1.php');

        $wrapper = new IncludedFilesStreamWrapper;
        $includedFiles = $wrapper->getIncludedFiles($file);

        $this->assertEquals([
            codecept_data_dir('wrap/file_2.php'),
        ], $includedFiles);
    }

    /**
     * It should allow setting code lines before file
     *
     * @test
     */
    public function should_allow_setting_code_lines_before_file()
    {
        $file = codecept_data_dir('wrap/file_1.php');

        $wrapper = new IncludedFilesStreamWrapper;
        $code = '$include3 = true;';

        $includedFiles = $wrapper->getIncludedFiles($file,$code);

        $this->assertEquals([
            codecept_data_dir('wrap/file_2.php'),
            codecept_data_dir('wrap/file_3.php'),
        ], $includedFiles);
    }
}
