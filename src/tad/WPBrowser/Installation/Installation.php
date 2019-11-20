<?php
/**
 * Models a WordPress installation.
 *
 * @since   TBD
 *
 * @package tad\WPBrowser\Installation
 */

namespace tad\WPBrowser\Installation;

use tad\WPBrowser\StreamWrappers\IncludedFilesStreamWrapper;
use tad\WPBrowser\StreamWrappers\StreamWrapperException;
use function tad\WPBrowser\camelCase;
use function tad\WPBrowser\findWpConfigFile;
use function tad\WPBrowser\pathNormalize;
use function tad\WPBrowser\pathJoin;

/**
 * Class Installation
 *
 * @package tad\WPBrowser\Installation
 */
class Installation
{
    /**
     * The absolute path to the WordPress installation.
     *
     * @var string
     */
    protected $wpRootDir;

    /**
     * The absolute path to the installation wp-config.php file.
     *
     * @var string
     */
    protected $wpConfigFile;

    /**
     * The absolute path to the wp-admin directory.
     *
     * @var string
     */
    protected $wpAdminDir;

    /**
     * Installation constructor.
     *
     * @param string $wpRootDir The absolute path to the WordPress installation.
     *
     * @throws InstallationException If the directory does not exist or does not look like a WordPress installation
     *                               directory.
     */
    public function __construct($wpRootDir)
    {
        $wpRootDirRealPath = realpath($wpRootDir);

        if (false === $wpRootDirRealPath) {
            throw new InstallationException(
                sprintf(
                    'wp-load.php file not found in "%s"; is the path correct?',
                    $wpRootDir
                )
            );
        }

        if (!file_exists(pathJoin($wpRootDirRealPath, 'wp-load.php'))) {
            // Are we pointing at the dir containing the index.php of an installation moved to sub-dir?
            $wpRootDir = $this->findWpRootDirFromIndexFile(pathJoin($wpRootDir, 'index.php'));
        }

        if (!$wpConfigFile = findWpConfigFile($wpRootDir)) {
            throw new InstallationException(sprintf(
                'wp-config.php file not found in "%s" or above; is this a WordPress installation?',
                $wpRootDir
            ));
        }

        $this->wpRootDir = $wpRootDir;
        $this->wpConfigFile = $wpConfigFile;

        if (!is_dir(pathJoin($this->wpRootDir, '/wp-admin'))) {
            throw new InstallationException(
                sprintf(
                    'wp-admin directory not found in "%s"; is the WordPress installation whole?',
                    $this->wpRootDir
                )
            );
        }

        $this->wpAdminDir = pathJoin($this->wpRootDir, 'wp-admin');
    }

    /**
     * Finds the absolute path to the WordPress root directory given the index.php file path that might be present
     * in the directory.
     *
     * @param string $indexFile The path to the index.php file of the candidate WordPress installation.
     *
     * @return string|false The path to the WordPress installation directory for the specified index file, or `false`.
     */
    protected function findWpRootDirFromIndexFile($indexFile)
    {
        if (!file_exists($indexFile)) {
            return false;
        }

        try {
            $includedFiles = (new IncludedFilesStreamWrapper())->getIncludedFiles($indexFile);
        } catch (StreamWrapperException $e) {
            return false;
        }

        $candidateFiles = array_values(array_filter($includedFiles, static function ($file) {
            return basename($file) === 'wp-blog-header.php';
        }));

        return count($candidateFiles) ? dirname($candidateFiles[0]) : false;
    }

    /**
     * Builds a WordPress installation representation from the path to the WordPress root directory.
     *
     * @param string $wpRootDir The path to the WordPress root installation directory.
     * @return NotFoundInstallation|static An installation instance.
     */
    public static function fromDir($wpRootDir)
    {
        $wpRootDirRealPath = realpath($wpRootDir);

        if ($wpRootDirRealPath === false) {
            return new NotFoundInstallation($wpRootDir);
        }

        try {
            return new static($wpRootDirRealPath);
        } catch (InstallationException $e) {
            return new NotFoundInstallation($wpRootDir);
        }
    }

    /**
     * Returns the path to the installation wp-config.php file.
     *
     * @return string The path to the installation wp-config.php file.
     */
    public function getWpConfigFile()
    {
        return $this->wpConfigFile;
    }

    /**
     * Returns the path to a file or directory in the installation.
     *
     * @param string $path The path, relative to the installation root directory.
     *
     * @return string The absolute path to the file or directory.
     */
    public function getDir($path = '/')
    {
        $normalized = trim(pathNormalize($path), '/');
        $slug = camelCase(basename($normalized, '.php'), true);

        $method = '';
        switch ($slug) {
            case 'WpConfig':
                $method = 'getWpConfigFile';
                break;
            default:
                $method = 'get' . $slug . 'Dir';
                break;
        }

        if ($method !== 'getDir' && method_exists($this, $method)) {
            return $this->{$method}();
        }

        return pathJoin($this->wpRootDir, $normalized);
    }

    /**
     * Returns the absolute path the wp-admin directory.
     *
     * @param string $path An optional path to append to the wp-admin directory path.
     * @return string The absolute path to the wp-admin directory.
     */
    public function getWpAdminDir($path = '/')
    {
        return pathJoin($this->wpAdminDir, $path);
    }
}
