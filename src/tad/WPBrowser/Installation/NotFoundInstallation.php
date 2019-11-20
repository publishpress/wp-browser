<?php
/**
 * Models a not found WordPress installation.
 *
 * @package tad\WPBrowser\Installation
 */

namespace tad\WPBrowser\Installation;

/**
 * Class NotFoundInstallation
 *
 * @package tad\WPBrowser\Installation
 */
class NotFoundInstallation
{
    /**
     * The path to the WordPress not found installation.j
     *
     * @var string
     */
    protected $wpRootDir;

    /**
     * NotFoundInstallation constructor.
     *
     * @param string $wpRootDir The absolute path to the WordPress installation, that does not exist.
     */
    public function __construct($wpRootDir)
    {
        $this->wpRootDir = $wpRootDir;
    }
}
