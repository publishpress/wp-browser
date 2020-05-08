<?php namespace lucatume\Cli\Traits;

class WithCliInputTest extends \Codeception\Test\Unit
{
    use WithCliInput;

    protected $stdinBackup;

    public function confirmationQuestionDataProvider()
    {
        return [
            'yes' => ["yes\n", true, true],
            'y' => ["y\n", true, true],
            'Y' => ["Y\n", true, true],
            'no' => ["no\n", true, false],
            'n' => ["n\n", true, false],
            'N' => ["N\n", true, false],
            'enter true' => ["\n", true, true],
            'enter false' => ["\n", false, false],
        ];
    }

    /**
     * It should allow asking for confirmation from the user
     *
     * @test
     * @dataProvider confirmationQuestionDataProvider
     */
    public function should_allow_asking_for_confirmation_from_the_user($reply, $default, $expected)
    {
        $mockStdin = fopen('php://memory', 'w+');
        fwrite($mockStdin, $reply . "\n");
        rewind($mockStdin);

        $this->setInputStream($mockStdin);

        $this->assertEquals($expected, $this->confirm('Question?', $default));
        fclose($mockStdin);
    }

    /**
     * It should allow asking for multiple confirmations from the user
     *
     * @test
     */
    public function should_allow_asking_for_multiple_confirmations_from_the_user()
    {
        $mockStdin = fopen('php://memory', 'w+');
        fwrite($mockStdin, implode("\n", ['y', '', 'n', 'n', '']));
        rewind($mockStdin);

        $this->setInputStream($mockStdin);

        $this->assertTrue($this->confirm('Question?'));
        $this->assertFalse($this->confirm('Question?', false));
        $this->assertFalse($this->confirm('Question?'));
        $this->assertFalse($this->confirm('Question?'));
        $this->assertTrue($this->confirm('Question?', true));
    }
}
