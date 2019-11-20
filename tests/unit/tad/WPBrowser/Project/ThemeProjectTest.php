<?php namespace tad\WPBrowser\Project;

class ThemeProjectTest extends \Codeception\Test\Unit
{
    /**
     * It should throw if root dir does not contain style.css file
     *
     * @test
     */
    public function should_throw_if_root_dir_does_not_contain_style_css_file()
    {
        $this->expectException(ProjectException::class);

        new ThemeProject(__DIR__);
    }

    /**
     * It should correctly build on theme dir
     *
     * @test
     */
    public function should_correctly_build_on_theme_dir()
    {
        new ThemeProject(codecept_data_dir('themes/dummy'));
    }
}
