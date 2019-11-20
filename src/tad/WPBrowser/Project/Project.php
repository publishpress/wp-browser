<?php
/**
 * Models a project that is subject to test.
 *
 * @package tad\WPBrowser\Project
 */

namespace tad\WPBrowser\Project;

use function tad\WPBrowser\findPluginFile;
use function tad\WPBrowser\findThemeStyleFile;
use function tad\WPBrowser\slug;

/**
 * Class Project
 *
 * @package tad\WPBrowser\Project
 */
class Project
{
    /**
     * The absolute path to the project root directory.
     *
     * @var string
     */
    protected $rootDir;

    /**
     * The project name.
     *
     * @var string
     */
    protected $name;

    /**
     * Project constructor.
     *
     * @param string $rootDir The path to the project root directory.
     *
     * @throws ProjectException If the directory does not exist or is not readable.
     */
    public function __construct($rootDir)
    {
        $rootDir = realpath($rootDir);

        if ($rootDir === false || !is_dir($rootDir)) {
            throw new ProjectException('The "' . $rootDir . '" does not exist or is not readable.');
        }

        $this->rootDir = realpath($rootDir);
    }

    /**
     * Builds the correct project depending on the root directory and the information found there.
     *
     * @param string $rootDir The project root directory.
     *
     * @return Project
     *
     * @throws ProjectException If the root directory is not found or a specific project is invalid.
     */
    public static function fromDir($rootDir)
    {
        if ($pluginFile = findPluginFile($rootDir)) {
            $project = new PluginProject(dirname($pluginFile), $pluginFile);
        } elseif ($themeStyleFile = findThemeStyleFile($rootDir)) {
            $project = new ThemeProject(dirname($themeStyleFile), $themeStyleFile);
        } else {
            $project = new static($rootDir);
        }

        $project->name = slug(basename($rootDir), '_');

        return $project;
    }

    public function name()
    {
        return $this->name;
    }
}
