<?php
/**
 * Models a theme project.
 *
 * @package tad\WPBrowser\Project
 */

namespace tad\WPBrowser\Project;

use function tad\WPBrowser\findThemeStyleFile;

/**
 * Class ThemeProject
 *
 * @package tad\WPBrowser\Project
 */
class ThemeProject extends Project
{
    /**
     * The absolute path to the theme style file.
     *
     * @var string
     */
    protected $styleFile;

    /**
     * ThemeProject constructor.
     *
     * @param string      $rootDir   The project root directory.
     * @param string|null $styleFile The path to the theme style.css file.
     *
     * @throws ProjectException If the root directory is not valid or this is not a theme.
     */
    public function __construct($rootDir, $styleFile = null)
    {
        parent::__construct($rootDir);
        $this->styleFile = $styleFile !== null ? $styleFile : findThemeStyleFile($this->rootDir);

        if ($this->styleFile === false) {
            throw new ProjectException('This does not appear to be a theme: "style.css" file not found.');
        }
    }
}
