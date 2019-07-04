<?php
/**
 * An exception thrown while running a process.
 *
 * @package tad\WPBrowser\Exceptions
 */

namespace tad\WPBrowser\Exceptions;

use Symfony\Component\Process\Process;

/**
 * Class ProcessException
 *
 * @package tad\WPBrowser\Exceptions
 */
class ProcessException extends \Exception
{

    /**
     * Builds and returns an exception to indicate a background process could not be started.
     *
     * @param \Symfony\Component\Process\Process $process The failed background process instance.
     *
     * @return ProcessException The built exception instance.
     */
    public static function becauseBackgroundProcessCouldNotBeStarted(Process $process)
    {
        return new static("Background process could not be started: {$process->getErrorOutput()}");
    }
}
