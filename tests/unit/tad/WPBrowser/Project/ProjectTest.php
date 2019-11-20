<?php namespace tad\WPBrowser\Project;

class ProjectTest extends \Codeception\Test\Unit
{
    /**
     * It should throw if the project dir does not exist or is not readable
     *
     * @test
     */
    public function should_throw_if_the_project_dir_does_not_exist_or_is_not_readable()
    {
        $this->expectException(ProjectException::class);

        new Project('test/test/test');
    }

    /**
     * It should correctly build on a plugin project
     *
     * @test
     */
    public function should_correctly_build_on_a_plugin_project()
    {
        $project = Project::fromDir(codecept_data_dir('plugins/test'));

        $this->assertInstanceOf(PluginProject::class, $project);
    }

    /**
     * It should correctly build on theme project
     *
     * @test
     */
    public function should_correctly_build_on_theme_project()
    {
        $project = Project::fromDir(codecept_data_dir('themes/dummy'));

        $this->assertInstanceOf(ThemeProject::class, $project);
    }

    public function project_dirs_data_set()
    {
        return [
            ['foo', 'foo'],
            ['foo bar', 'foo_bar'],
            ['Lorem Dolor', 'lorem_dolor'],
            ['test-baz', 'test_baz'],
        ];
    }

    /**
     * It should allow getting the project name
     *
     * @test
     * @dataProvider project_dirs_data_set
     */
    public function should_allow_getting_the_project_name($projectDir, $expected)
    {
        $rootDir = sys_get_temp_dir() . '/' . $projectDir;
        if (!is_dir($rootDir)) {
            mkdir($rootDir, 0777);
        }

        $this->assertEquals($expected, Project::fromDir($rootDir)->name());
    }
}
