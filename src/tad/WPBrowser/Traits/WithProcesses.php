<?php
/**
 * Provides methods to use and manage Symfony Processes.
 *
 * @package tad\WPBrowser\Traits
 */

namespace tad\WPBrowser\Traits;

use Symfony\Component\Process\Process;
use tad\WPBrowser\Exceptions\ProcessException;

/**
 * Trait WithProcesses
 *
 * @package tad\WPBrowser\Traits
 */
trait WithProcesses
{
    /**
     * Starts a background process and returns it.
     *
     * @param array                   $command        The command to execute.
     * @param null                    $cwd            The current working directory, if not provided it will be set to
     *                                                the current one.
     * @param null|int|float|callable $sleepOrVerify  Either a callable or a numeric value. In the first case the
     *                                                method
     *                                                will sleep the specified amount of seconds before returning, in
     *                                                the second case the method will run the verification callback
     *                                                every 50ms until it passes, returning a truthy value, before
     *                                                returning.
     *
     * @return Process The background process handle.
     * @throws ProcessException If there's an error while starting the process.
     */
    protected function executeBackgroundProcess(array $command, $cwd = null, $sleepOrVerify = null)
    {
        $process = new Process($command, $cwd);
        $process->setTimeout(null);
        $process->start();

        // Whatever happens let's make sure any background process is killed at shutdown.
        register_shutdown_function(static function () use ($process) {
            if ($process->isRunning()) {
                $process->stop();
            }
        });

        if ($process->getErrorOutput()) {
            $process->stop();
            throw ProcessException::becauseBackgroundProcessCouldNotBeStarted($process);
        }

        if (is_callable($sleepOrVerify)) {
            while (!$sleepOrVerify($process)) {
                codecept_debug('Background process not ready yet, sleeping 50ms...');
                usleep(50000);
            }
        } elseif (is_numeric($sleepOrVerify)) {
            codecept_debug("Sleeping {$sleepOrVerify}s after background process started...");
            sleep((float)$sleepOrVerify);
        }

        return $process;
    }
}
