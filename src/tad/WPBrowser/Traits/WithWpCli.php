<?php
/**
 * Provides methods to interact with wp-cli binaries and files.
 *
 * @package tad\WPBrowser\Traits
 */

namespace tad\WPBrowser\Traits;

use tad\WPBrowser\Environment\Executor;
use WP_CLI\Configurator;

/**
 * Class WithWpCli
 *
 * @package tad\WPBrowser\Traits
 */
trait WithWpCli
{
    /**
     * The absolute path to the wp-cli package root directory.
     *
     * @var string.
     */
    protected $wpCliRootDir;

    /**
     * The absolute path to the WordPress installation root folder.
     *
     * @var string
     */
    protected $path;

    /**
     * Returns the absolute path the the wp-cli boot file.
     *
     * @return string The absolute path the the wp-cli boot file.
     *
     * @throws \RuntimeException If the path to the WP_CLI\Configurator class cannot be resolved.
     */
    protected function getWpCliBootFile()
    {
        return $this->getWpCliRootDir('/php/boot-fs.php');
    }

    /**
     * Returns the absolute path to the wp-cli package root directory.
     *
     * @param string|null $path A path to append to the root directory.
     *
     * @return string The absolute path to the wp-cli package root directory.
     *
     * @throws \RuntimeException If the path to the WP_CLI\Configurator class cannot be resolved.
     */
    protected function getWpCliRootDir($path = null)
    {
        if ($this->wpCliRootDir === null) {
            try {
                $ref = new \ReflectionClass(Configurator::class);
            } catch (\ReflectionException $e) {
                throw new \RuntimeException('Could not find the path to embedded WPCLI Configurator class');
            }

            $wpCliRootDir = dirname($ref->getFileName()) . '/../../';

            $wpCliRootRealPath = realpath($wpCliRootDir);

            if (!empty($wpCliRootRealPath)) {
                $wpCliRootDir = $wpCliRootRealPath;
            }

            $this->wpCliRootDir = $wpCliRootDir;
        }

        return $path ?
            rtrim($this->wpCliRootDir, '\\/') . DIRECTORY_SEPARATOR . ltrim($path, '\\/')
            : $this->wpCliRootDir;
    }

    /**
     * Requires some wp-cli package files that could not be autoloaded.
     */
    protected function requireWpCliFiles()
    {
        if (!defined('WP_CLI_ROOT')) {
            define('WP_CLI_ROOT', $this->getWpCliRootDir());
        }
        require_once $this->getWpCliRootDir('/php/utils.php');
        require_once $this->getWpCliRootDir('/php/class-wp-cli.php');
        require_once $this->getWpCliRootDir('/php/class-wp-cli-command.php');
    }

    /**
     * Sets up the cli.
     *
     * @param string $wpRootFolderDir The absolute path to the WordPress installation root directory.
     */
    protected function setUpWpCli($wpRootFolderDir)
    {
        $this->path = $wpRootFolderDir;
    }

    /**
     * Executes a wp-cli command.
     *
     * @param array $command The command fragments; a mix of arguments and options. The `path` option will be always set
     *                       from the `setUpWpCli` method.
     */
    protected function executeWpCliCommand(array $command = ['version'])
    {
        $commandString = implode(' ', $command);
        $this->executor = $this->executor ?: new Executor();
        $status = $this->executor->exec($command, $output);
    }

    /**
     * Formats an associative array of options to be used as wp-cli options.
     *
     * @param array $options The array of wp-cli options to format.
     *
     * @return array The formatted array of wp-cli options, in the `[ --<key> <value> ]` format.
     */
    protected function wpCliOptions(array $options)
    {
        return array_merge(
            array_combine(
                array_map(static function ($key) {
                    return escapeshellarg('--' . ltrim($key, '-') . $key);
                }, array_keys($options)),
                array_map('escapeshellarg', $options)
            ),
            ['--path' => escapeshellarg($this->path)]
        );
    }
}
