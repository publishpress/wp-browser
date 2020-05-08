<?php
/**
 * Models a process handled by the ClI application.
 *
 * @package lucatume\Cli\Models
 */

namespace lucatume\Cli\Models;

/**
 * Class Process
 *
 * @package lucatume\Cli\Models
 */
class Process
{
    /**
     * The process arguments.
     *
     * @var array<string>
     */
    protected $command;

    /**
     * Whether the process did run already or not.
     *
     * @var bool
     */
    protected $didRun = false;

    /**
     * The process output in array format.
     *
     * @var array<string>
     */
    protected $output = [];

    /**
     * The process exit status.
     *
     * @var string
     */
    protected $exitStatus = 0;

    /**
     * The callable that will handle the process synchronous execution.
     *
     * @var callable
     */
    protected $syncHandler;

    /**
     * Process constructor.
     *
     * @param array<string> $command The process arguments.
     */
    public function __construct(array $command)
    {
        $this->command = $command;
        $this->syncHandler = $this->getDefaultSyncHandler();
    }

    /**
     * Returns the process default synchronous process handler.
     *
     * @return \Closure The process default synchronous process handler.
     */
    protected function getDefaultSyncHandler()
    {
        return function (array $command, array &$output, &$exitStatus) {
            exec($this->buildCommandString($command), $output, $exitStatus);
        };
    }

    /**
     * Builds the process command string.
     *
     * @param array<string> $command The process command, in array format.
     *
     * @return string The built command string.
     */
    protected function buildCommandString(array $command)
    {
        return escapeshellcmd(implode(' ', $command));
    }

    /**
     * Returns the process output.
     *
     * @return array<string> The process output in array format.
     */
    public function getOutput()
    {
        $this->lazyRun();

        return $this->output;
    }

    /**
     * Runs the process when and if required.
     */
    protected function lazyRun()
    {
        if (!$this->didRun) {
            $this->run();
        }
    }

    /**
     * Runs the process in synchronous and blocking mode.
     *
     * @return int The process exit status, `0` means success.
     */
    public function run()
    {
        $output = [];
        $exitStatus = 0;
        $command = $this->command;
        $f = static function (callable $syncHandler) use ($command, &$output, &$exitStatus) {
            $syncHandler($command, $output, $exitStatus);
        };
        $f($this->syncHandler);
        $this->output = (array)$output;
        $this->exitStatus = (int)$exitStatus;
        $this->didRun = true;

        return $exitStatus;
    }

    /**
     * Sets the process sync handler.
     *
     * By default synchronous execution will be handled by the `exec` function.
     *
     * @param callable $syncHandler The callable that will handle the process synchronous execution.
     */
    public function setSyncHandler(callable $syncHandler)
    {
        $this->syncHandler = $syncHandler;
    }

    /**
     * Checks the current process exit status.
     *
     * @param int $expectedStatus The expected process exit status.
     *
     * @return $this The process instance to allow chaining.
     *
     * @throws \RuntimeException If the process exit status does not match the expected one.
     */
    public function checkStatus($expectedStatus = 0)
    {
        $this->lazyRun();

        if ($this->exitStatus === (int)$expectedStatus) {
            return $this;
        }

        $command = $this->buildCommandString($this->command);
        throw new \RuntimeException("Process exit status for command '{$command}' is {$this->exitStatus}, expected {$expectedStatus}.");
    }

    /**
     * Returns the process output in string format.
     *
     * @return string The process output in string format.
     */
    public function getStringOutput()
    {
        return implode("\n", $this->output);
    }
}
