<?php namespace lucatume\Cli;

use tad\Codeception\SnapshotAssertions\SnapshotAssertions;

class AppTest extends \Codeception\Test\Unit
{
    use SnapshotAssertions;

    /**
     * It should allow giving the app a name and a version.
     *
     * @test
     */
    public function should_allow_giving_the_app_a_name_and_a_version()
    {
        $app = new App('test', '1.0.0');

        $this->assertEquals('test', $app->getName());
    }

    /**
     * It should allow adding commands to the application
     *
     * @test
     */
    public function should_allow_adding_commands_to_the_application()
    {
        $app = new App('test', '1.0.0');
        $command = new Command('greet', ['name' => '_help_']);
        $app->addCommand($command);
        $helpCommand = $app->getHelpCommand();

        $this->assertEquals(['help' => $helpCommand, 'greet' => $command], $app->getCommands());
        $this->assertSame($helpCommand, $app->getDefaultCommand());
    }

    /**
     * It should correctly format the application help
     *
     * @test
     */
    public function should_correctly_format_the_application_help()
    {
        $app = new App('test', '1.0.0');
        $command = new Command('greet', ['name*' => 'The name(s) to greet.']);
        $output = '';
        $command->setOutputHandler(static function ($message) use (&$output) {
            $output .= $message;
        });
        $app->addCommand($command);
        $app->help();

        $this->assertMatchesStringSnapshot($output);
    }
}
