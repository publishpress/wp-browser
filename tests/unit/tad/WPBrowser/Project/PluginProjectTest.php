<?php namespace tad\WPBrowser\Project;

class PluginProjectTest extends \Codeception\Test\Unit
{
    /**
     * It should throw if building not on plugin dir
     *
     * @test
     */
    public function should_throw_if_building_not_on_plugin_dir()
    {
        $this->expectException(ProjectException::class);

        new PluginProject(__DIR__);
    }
}
