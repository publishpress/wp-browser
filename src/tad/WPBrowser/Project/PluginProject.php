<?php
/**
 * Models a plugin project.
 *
 * @package tad\WPBrowser\Project
 */

namespace tad\WPBrowser\Project;

use function tad\WPBrowser\findPluginFile;

/**
 * Class PluginProject
 *
 * @since   TBD
 *
 * @package tad\WPBrowser\Project
 */
class PluginProject extends Project

{
    /**
     * PluginProject constructor.
     *
     * @param string      $rootDir    The project root directory.
     * @param string|null $pluginFile The path to the main plugin file.
     * @throws ProjectException If the root directory is not valid or this is not a plugin.
     */
    public function __construct($rootDir, $pluginFile = null)
    {
        parent::__construct($rootDir);
        $this->pluginFile = $pluginFile !== null ? $pluginFile : findPluginFile($this->rootDir);
        if ($this->pluginFile === false) {
            throw new ProjectException('This does not appear to be a plugin: main plugin file not found.');
        }
    }
}
