<?php namespace lucatume\Cli;

use tad\Codeception\SnapshotAssertions\SnapshotAssertions;

class AppTest extends \Codeception\Test\Unit
{
    use SnapshotAssertions;

    /**
     * @var array
     */
    protected $argvBackup;

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
        $command = new Command('greet', 'Greet', ['name' => '_help_']);
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
        $command = new Command('greet', 'Greet', ['name*' => 'The name(s) to greet.']);
        $output = '';
        $app->setOutputHandler(static function ($message) use (&$output) {
            $output .= $message;
        });
        $app->addCommand($command);
        $app->printHelp();

        $this->assertMatchesStringSnapshot($output);
    }

    /**
     * It should allow parsing the input or else
     *
     * @test
     */
    public function should_allow_parsing_the_input_or_else()
    {
        $app = new App('test', '1.0.0');
        $command = new Command('greet', 'Greet', ['name*' => 'The name(s) to greet.']);
        $output = '';
        $app->setOutputHandler(static function ($message) use (&$output) {
            $output .= $message;
        });
        $app->addCommand($command);
        $GLOBALS['argv'] = ['/path/app.php', 'foo', 'bar'];

        $called = false;
        $errorMessage = '';
        $app->parseElse(static function ($message) use (&$called, &$errorMessage) {
            $called = true;
            $errorMessage = $message;
        });

        $this->assertTrue($called);
        $this->assertMatchesStringSnapshot($errorMessage);
    }

    /**
     * It should allow printing the help message on error
     *
     * @test
     */
    public function should_allow_printing_the_help_message_on_error()
    {
        $app = new App('test', '1.0.0');
        $command = new Command('greet', 'Greet', ['name*' => 'The name(s) to greet.']);
        $output = '';
        $outputHandler = static function ($message) use (&$output) {
            $output .= $message;
        };
        $app->setOutputHandler($outputHandler);
        $app->addCommand($command);

        $app->parseElse(static function ($message) use ($app, $outputHandler) {
            $outputHandler($message . PHP_EOL);
            $app->printHelp();
        }, ['foo', 'bar']);

        $this->assertMatchesStringSnapshot($output);
    }

    /**
     * It should allow getting the command and its args w/ parse
     *
     * @test
     */
    public function should_allow_getting_the_command_and_its_args_w_parse()
    {
        $app = new App('test', '1.0.0');
        $command = new Command('greet', 'Greet', ['name*' => 'The name(s) to greet.']);
        $output = '';
        $outputHandler = static function ($message) use (&$output) {
            $output .= $message;
        };
        $app->setOutputHandler($outputHandler);
        $app->addCommand($command);

        $args = $app->parseElse(static function ($message) use ($app, $outputHandler) {
            $outputHandler($message . PHP_EOL);
            $app->printHelp();
        }, ['greet', 'bob']);

        $this->assertEquals('greet', $args('command'));
        $this->assertEquals(['bob'], $args('name'));
    }

    protected function setUp()
    {
        return parent::setUp();
        global $argv;
        $this->argvBackup = $argv;
    }

    protected function tearDown()
    {
        global $argv;
        $argv = $this->argvBackup;
        parent::tearDown();
    }
}
