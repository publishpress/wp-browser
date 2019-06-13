<?php
/**
 * Provides methods to interact with wp-cli binaries and files.
 *
 * @package tad\WPBrowser\Traits
 */

namespace tad\WPBrowser\Traits;

use tad\WPBrowser\Adapters\Process;
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
    protected $wpCliWpRootDir;

    /**
     * The process adapter the implementation will use.
     *
     * @var Process
     */
    protected $wpCliProcess;

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
     * Sets up the cli.
     *
     * @param string       $wpRootFolderDir The absolute path to the WordPress installation root directory.
     * @param Process|null $process         The process wrapper instance to use.
     */
    protected function setUpWpCli($wpRootFolderDir, Process $process = null)
    {
        $this->wpCliWpRootDir = $wpRootFolderDir;
        $this->wpCliProcess = $process ?: new Process();
    }

    /**
     * Executes a wp-cli command.
     *
     * @param array          $command   The command fragments; a mix of arguments and options. The `path` option will
     *                                  be always set from the `setUpWpCli` method.
     * @param int|float|null $timeout   The timeout, in seconds, to use for the command. Use `null` to remove the
     *                                  timeout entirely.
     *
     * @return \Symfony\Component\Process\Process The process object that executed the command.
     */
    protected function executeWpCliCommand(array $command = ['version'], $timeout = 60)
    {
        $fullCommand = $this->buildFullCommand($command);
        $process = $this->wpCliProcess->forCommand($fullCommand, $this->wpCliWpRootDir);
        $process->setTimeout($timeout);
        $process->run();

        return $process;
    }

    /**
     * Builds the full command to run including the PHP binary and the wp-cli boot file path.
     *
     * @param array|string $command The command to run.
     *
     * @return array The full command.
     */
    public function buildFullCommand($command)
    {
        $fullCommand = array_merge([
            escapeshellarg(PHP_BINARY),
            escapeshellarg($this->getWpCliBootFilePath())
        ], (array)$command);
        return $fullCommand;
    }

    /**
     * Returns the absolute path the the wp-cli boot file.
     *
     * @return string The absolute path the the wp-cli boot file.
     *
     * @throws \RuntimeException If the path to the WP_CLI\Configurator class cannot be resolved.
     */
    protected function getWpCliBootFilePath()
    {
        return $this->getWpCliRootDir('/php/boot-fs.php');
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
        $buffer = [];

        foreach ($options as $key => $value) {
            if ($value !== true) {
                // Normal options.
                $buffer [] = '--' . ltrim($key, '-') . '=' . escapeshellarg($value);
            } else {
                // Flag options.
                $buffer [] = '--' . ltrim($key, '-');
            }
        }

        return $buffer;
    }

    /**
     * Parses the inline options found in a command and returns them in an associative array.
     *
     * @param string|array $command The command to parse.
     *
     * @return array An associative array of all the options found in the command.
     */
    protected function parseWpCliInlineOptions($command)
    {
        $parsed = [];

        foreach ((array)$command as $c) {
            $pattern = '/--(?<key>[^=]*?)=(?<value>([\'"]{1}.*?[\'"]{1})|.*?)(?=(\\s+|$))/um';
            preg_match_all($pattern, $c, $matches);
            $keys = isset($matches['key']) ? (array)$matches['key'] : [];
            $values = isset($matches['value']) ? (array)$matches['value'] : [];
            $parsed[] = array_combine($keys, $values);
        }

        $parsed = array_filter($parsed);

        return count($parsed) ? array_merge(...array_filter($parsed)) : [];
    }
}
