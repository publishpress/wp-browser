<?php
/**
 * Provides methods to run and interact with external processes.
 *
 * @package lucatume\Cli\Traits
 */

namespace lucatume\Cli\Traits;

use lucatume\Cli\Models\Process;

/**
 * Trait WithProcesses
 *
 * @package lucatume\Cli\Traits
 */
trait WithProcesses
{
    /**
     * The current sync handler that will be set for all processes.
     * @var callable
     */
    protected $processSyncHandler;

    /**
     * Shorthand to setup a process, execute it and return its output.
     *
     * Note that, differently from the `exec` function, this method will return all the process output, not just the
     * last line.
     *
     * @param array $command The command to execute.
     *
     * @return string The command output in string format.
     */
    public function exec(array $command)
    {
        return $this->process($command)->checkStatus(0)->getStringOutput();
    }

    /**
     * Builds a process and sets it up.
     *
     * @param array<string> $command The command, in array format, e.g. `['git','status']`.
     *
     * @return Process A process handler instance, the process did not run yet.
     */
    public function process(array $command)
    {
        $process = new Process($command);

        if (isset($this->processSyncHandler)) {
            $process->setSyncHandler($this->processSyncHandler);
        }

        return $process;
    }

    /**
     * Sets the callback that will handle all the synchronous processes.
     *
     * @param callable $syncHandler The callback that will handle all the synchronous processes. The callback will
     *                              should have the following signature: `array $command, array &$output, &$exitStatus`.
     */
    protected function setProcessSyncHandler(callable $syncHandler)
    {
        $this->processSyncHandler = $syncHandler;
    }
}
