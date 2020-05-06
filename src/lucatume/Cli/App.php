<?php
/**
 * The base class of a CLI application.
 *
 * @package lucatume\Cli
 */

namespace lucatume\Cli;

use lucatume\Cli\Interfaces\Helper;

/**
 * Class App
 *
 * @package lucatume\Cli
 */
class App implements Helper
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
     * The application input, defaults to STDIN if not provided.
     *
     * @var callable
     */
    protected $inputProvider;

    /**
     * App constructor.
     *
     * @param string $name The application name;
     * @param string $version The current application version.
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
     * {@inheritDoc}
     */
    public function printHelp()
    {
        $this->output($this->getHelp());
    }

    /**
     * {@inheritDoc}
     */
    public function getHelp()
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

        $pieces = array_map(function (Command $command) use ($pad) {
            $text = '<green>' . str_pad($command->getName(), $pad) . '</green>' . $command->getDescription();

            return $this->style($text);
        }, $this->commands);

        $commandsHelp = implode(PHP_EOL, $pieces);

        return $this->style(sprintf(
            $help,
            $this->name,
            $this->version,
            $commandsHelp
        ));
    }

    /**
     * Returns whether a command is registered in the application or not.
     *
     * @param string $command The name of the command to check.
     *
     * @return bool Whether a command is registered in the application or not.
     */
    protected function hasCommand($command)
    {
        return array_key_exists($command, $this->commands);
    }

    /**
     * Returns a command registered in the app.
     *
     * @param string $command The name of the command to return.
     *
     * @return Command The command instance.
     *
     * @throws CliException If there is no command with the specified name registered in the application.
     */
    protected function getCommand($command)
    {
        if (!$this->hasCommand($command)) {
            throw CliException::becauseTheCommandIsNotDefined($this->name, $command);
        }

        return $this->commands[$command];
    }

    /**
     * Parses the application input and returns a parsed argument and option map.
     *
     * @param callable $else The callback to call if there's any issue parsing the input.
     * @param array|null $args The argument input to parse.
     *
     * @return Args The parsed input arguments.
     */
    public function parseElse(callable $else, array $args = null)
    {
        if ($args === null) {
            global $argv;
            $args = $argv;
            // Remove the first entry, the app file path.
            array_shift($args);
        }

        $commandName = array_shift($args);

        if (null === $commandName) {
            $else("No command provided.");
            return new Args(['command' => $commandName]);
        }

        try {
            $command = $this->getCommand($commandName);
            $parsed = $command->parseInput($args);
            $parsed['command'] = $commandName;
            return $parsed;
        } catch (CliException $e) {
            $else($e->getMessage());
        }

        return new Args(['command' => $commandName]);
    }
}
