<?php
/**
 * The base class of a CLI application.
 *
 * @package lucatume\Cli
 */

namespace lucatume\Cli;

/**
 * Class App
 *
 * @package lucatume\Cli
 */
class App
{
    use Traits\WithCliOutput;

    const OUTPUT_DEFAULT = 'default';

    /**
     * The application name.
     *
     * @var string
     */
    protected $name;

    /**
     * The current application version.
     *
     * @var string
     */
    protected $version = '1.0.0';

    /**
     * The current application commands.
     *
     * @var array<Command>
     */
    protected $commands = [];

    /**
     * The default application command.
     *
     * @var Command
     */
    protected $defaultCommand = false;

    /**
     * The application help command.
     *
     * @var Command
     */
    protected $helpCommand;

    /**
     * App constructor.
     *
     * @param string $name The application name;
     */
    public function __construct($name, $version)
    {
        $this->name = $name;
        $this->version = $version;
        $helpCommand = new Command('help', 'Displays the application help text.');
        $this->commands['help'] = $helpCommand;
        $this->defaultCommand = $helpCommand;
        $this->helpCommand = $helpCommand;
        $this->outputHandler = $this->getDefaultOutputHandler();
    }

    /**
     * Returns the application name.
     *
     * @return string The application name.
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Returns the current application version.
     *
     * @return string The current application version.
     */
    public function getVersion()
    {
        return $this->version;
    }

    public function addCommand(Command $command)
    {
        $this->commands[$command->getName()] = $command;
    }

    /**
     * Returns the application commands.
     *
     * @return array<Command> The current application commands.
     */
    public function getCommands()
    {
        return $this->commands;
    }

    /**
     * Returns the application default command.
     *
     * @return Command
     */
    public function getDefaultCommand()
    {
        return $this->defaultCommand;
    }

    /**
     * Returns the current application help command.
     *
     * @return Command The current application help command.
     */
    public function getHelpCommand()
    {
        return $this->helpCommand;
    }

    /**
     * Prints the application help text to the current output handler.
     *
     * @since TBD
     *
     */
    public function help()
    {
        $help = <<< HELP_TEMPLATE

<light_blue>%s</light_blue> - <bold>version</bold> %s

Commands:

%s

HELP_TEMPLATE;

        $commandNames = array_values(array_map(static function (Command $command) {
            return $command->getName();
        }, $this->commands));

        $pad = max(20, ...array_map('strlen', $commandNames)) + 6;

        $commandsHelp = implode(PHP_EOL, array_map(function (Command $command) use ($pad) {
            return $this->style('<green>' . str_pad($command->getName(), $pad) . '</green>' . $command->getDescription());
        }, $this->commands));

        $this->output(sprintf(
            $help,
            $this->name,
            $this->version,
            $commandsHelp
        ));
    }
}
