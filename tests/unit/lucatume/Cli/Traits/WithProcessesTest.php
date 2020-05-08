<?php namespace lucatume\Cli\Traits;

class WithProcessesTest extends \Codeception\Test\Unit
{
    use WithProcesses;

    /**
     * It should throw if a process exit status is not the expected one.
     *
     * @test
     */
    public function should_throw_if_a_process_exit_status_is_not_the_expected_one()
    {
        $this->expectException(\RuntimeException::class);

        $this->setProcessSyncHandler(static function (array $command, array &$output, &$exitStatus) {
            $exitStatus = 1;
        });

        $this->process(['foo', 'bar', '--baz'])->checkStatus(0);
    }

    /**
     * It should allow getting a process output in array and string format
     *
     * @test
     */
    public function should_allow_getting_a_process_output_in_array_and_string_format()
    {
        $this->setProcessSyncHandler(static function (array $command, array &$output, &$exitStatus) {
            $exitStatus = 0;
            $output = ['lorem', 'dolor', 'sit'];
        });

        $process = $this->process(['foo', 'bar', '--baz'])->checkStatus(0);
        $this->assertEquals(['lorem', 'dolor', 'sit'], $process->getOutput());
        $this->assertEquals("lorem\ndolor\nsit", $process->getStringOutput());
    }

    /**
     * It should allow executing a process and getting its output
     *
     * @test
     */
    public function should_allow_executing_a_process_and_getting_its_output()
    {
        $this->setProcessSyncHandler(static function (array $command, array &$output, &$exitStatus) {
            $exitStatus = 0;
            $output = ['lorem', 'dolor', 'sit'];
        });

        $this->assertEquals("lorem\ndolor\nsit", $this->exec(['foo', 'bar', '--baz']));
    }
}
