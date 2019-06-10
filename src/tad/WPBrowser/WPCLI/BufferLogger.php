<?php
/**
 * A wp-cli logger implementation to collect logs into an array..
 *
 * @package tad\WPBrowser\WPCLI
 */

namespace tad\WPBrowser\WPCLI;

use WP_CLI\Loggers\Base;

/**
 * Class BufferLogger
 *
 * @package tad\WPBrowser\WPCLI
 */
class BufferLogger extends Base
{
    const ALL = 'all';
    const DEBUG = 'debug';
    const INFO = 'info';
    const SUCCESS = 'success';
    const WARNING = 'warning';

    /**
     * The array log.
     *
     * @var array
     */
    protected $log = [
        'all' => [],
        'debug' => [],
        'info' => [],
        'warning' => [],
        'success' => [],
    ];

    /**
     * Logs an information.
     *
     * @param string $message The information to log.
     */
    public function info($message)
    {
        $this->debug($message, static::INFO);
        $this->log[static::ALL][] = $message;
    }

    /**
     * Logs a debug message.
     *
     * @param string      $message The message to log.
     * @param bool|string $group   If specified then the message will be logged to this type, else to the `debug` stack.
     */
    public function debug($message, $group = false)
    {
        $group = $group ?: static::DEBUG;

        $this->checkGroup($group);

        $fullMessage = '[' . strtoupper($group) . '] ' . $message;
        $this->log[$group][] = $fullMessage;
        $this->log[static::ALL][] = $fullMessage;
    }

    /**
     * Checks a specified group is valid.
     *
     * @param string $group The group to check
     */
    protected function checkGroup($group)
    {
        if (!in_array($group, [
            static::DEBUG,
            static::INFO,
            static::SUCCESS,
            static::WARNING
        ], true)) {
            throw new \InvalidArgumentException("Group log '{$group}' is not valid.");
        }
    }

    /**
     * Logs a success message.
     *
     * @param string $message The message to log.
     */
    public function success($message)
    {
        $this->debug($message, static::SUCCESS);
        $this->log[static::ALL][] = $message;
    }

    /**
     * Logs a warning messsage.
     *
     * @param string $message The warning to log.
     */
    public function warning($message)
    {
        $this->debug($message, static::WARNING);
        $this->log[static::ALL][] = $message;
    }

    /**
     * Returns all the logs, in chronological order, or only logs of a group.
     *
     * @param null|string $group The group of logs to return or `null` to return all of them.
     *
     * @return array An array of logged messages.
     */
    public function getLogs($group = null)
    {
        if ($group !== null) {
            $this->checkGroup($group);
            return $this->log[$group];
        }
        return $this->log[static::ALL];
    }
}
