<?php
/**
 * Models a command, part of a CLI Application.
 *
 * @package lucatume\Cli
 */

namespace lucatume\Cli;

use lucatume\Cli\Interfaces\Helper;

/**
 * Class Command
 *
 * @package lucatume\Cli
 */
class Command implements Helper
{
    use Traits\WithCliOutput;

    /**
     * The command name.
     *
     * @var string
     */
    protected $name;

    /**
     * The parsed command arguments.
     *
     * @var array<array>
     */
    protected $args = [];

    /**
     * The parsed argument options.
     *
     * @var array<array>
     */
    protected $options = [];
    /**
     * The command definition and help text.
     *
     * @var array<string,string>
     */
    protected $definition;

    /**
     * The command short description.
     *
     * @var string
     */
    protected $description;

    /**
     * Command constructor.
     *
     * @param string $name The command name.
     * @param string $description The command short description.
     * @param array<string,string> $definition The map of the command definitions of arguments and options and the
     *                                         description.
     * @param callable|null $output The current output handler for the command. Echo if not provided.
     *
     * @throws CliException If the definition format is not correct.
     */
    public function __construct($name, $description, array $definition = [], $output = null)
    {
        if (count(array_filter(array_keys($definition), 'is_string')) !== count($definition)) {
            throw CliException::becauseEachDefinitionEntryShouldBeAMapElement();
        }
        $this->name = $name;

        if (!is_string($description)) {
            throw CliException::becauseArgumentShouldBeType('description', 'string');
        }

        $this->description = $description;

        if (!isset($definition['[--help]'])) {
            $definition['[--help]'] = 'Display the command help text';
        }

        $this->definition = $definition;

        $this->outputHandler = $this->getDefaultOutputHandler();
        $this->parseDefinition($definition);
    }

    /**
     * Parses the command definition for arguments and options.
     *
     * @param array<string,string> $definition The command map of argument and option definition and their description.
     */
    protected function parseDefinition(array $definition)
    {
        $options = [];
        $args = [];
        $haveMulti = false;

        foreach (array_keys($definition) as $entry) {
            if (0 === strpos($entry, '[-')) {
                // Option.
                $matchesOption = preg_match(
                    '/\\[(?<short>-\\w)*\\|*(?<long>--[\\w_-]+)*(?<req_value>=)*](?<multi>\\*)*/us',
                    $entry,
                    $m
                );
                if ($matchesOption) {
                    if (empty($m['long']) && empty($m['short'])) {
                        throw CliException::becauseDefinitionEntryDoesNotMatchAnyPattern($entry);
                    }
                    $options[] = [
                        'signature' => $entry,
                        'short' => !empty($m['short']) ? substr($m['short'], 1) : null,
                        'long' => !empty($m['long']) ? substr($m['long'], 2) : null,
                        'flag' => empty($m['req_value']),
                        'multi' => !empty($m['multi']),
                    ];

                    continue;
                }
            } else {
                // Argument.
                if (preg_match('/^(?<opt_o>\\[)*(?<name>[\\w_-]+)(?<opt_c>])*(?<multi>\\*)*/us', $entry, $m)) {
                    if (!empty($m['opt_o']) xor !empty($m['opt_c'])) {
                        throw CliException::becauseDefinitionEntryDoesNotMatchAnyPattern($entry);
                    }

                    if ($haveMulti) {
                        throw CliException::becauseMultiArgumentsShouldBeLast();
                    }

                    $haveMulti = !empty($m['multi']) ? $entry : false;

                    $args[] = [
                        'name' => $m ['name'],
                        'signature' => $entry,
                        'optional' => !empty($m['opt_c']),
                        'multi' => !empty($m['multi']),
                    ];
                    continue;
                }
            }

            throw CliException::becauseDefinitionEntryDoesNotMatchAnyPattern($entry);
        }

        usort($options, static function (array $a, array $b) {
            return strcasecmp($a['signature'], $b['signature']);
        });

        $this->args = $args;
        $this->options = $options;
    }

    /**
     * Parses the input and exits with a CLI-friendly message on error.
     *
     * @param array<mixed>|null $input The input to parse or `null` to use the global script arguements.
     * @param int $exitCode The exit code to use on failure.
     */
    public function parseInputOrExit(array $input = null, $exitCode = 1)
    {
        try {
            return $this->parseInput($input);
        } catch (CliException $e) {
            $this->output($e->getMessage());
            exit($exitCode);
        }
    }

    /**
     * Parses and validates the input arguments and options and returns a map of the validated argument and option
     * values.
     *
     * @param array<string>|null $input The current command input, or `null` to use the global script `$argv`.
     *
     * @return Map The parsed arguments.
     *
     * @throws CliException If the input arguments do not satisfy the command definition.
     */
    public function parseInput(array $input = null)
    {
        if ($input === null) {
            // If the input is not set, then use the global argument scripts.
            global $argv;
            $input = array_slice($argv, 1);
        }

        $argsCount = count($input);

        $args = $this->args;
        $options = array_reduce($this->options, static function (array $options, array $option) {
            foreach (['short', 'long'] as $k) {
                if (!empty($option[$k])) {
                    $options[$option[$k]] = $option;
                }
            }

            return $options;
        }, []);

        // Prime the options setting any flag option to `false`.
        $parsedOptions = array_reduce($options,static function(array $acc, array $option){
            if (!empty($option['flag'])) {
                // Prime flag options to `false`.
                foreach (['short', 'long'] as $version) {
                    if (isset($option[$version])) {
                        $acc[$option[$version]] = false;
                    }
                }
            }

            if(!empty($option['multi'])){
                // Prime multi options to empty arrays.
                foreach (['short', 'long'] as $version) {
                    if (isset($option[$version])) {
                        $acc[$option[$version]] = [];
                    }
                }
            }

            return $acc;
        }, []);

        $parsedArgs = [];
        $argsIndex = 0;

        for ($i = 0; $i < $argsCount; $i++) {
            if (preg_match('/^(--(?<long>[^=]+)|-(?<short>[^=]+))(=(?<value>.*))*$/usm', $input[$i], $match)) {
                // Parse an option.
                $option = !empty($match['short']) ? $match['short'] : $match['long'];
                $altName = !empty($match['short']) ? $options[$option]['long'] : $options[$option]['short'];
                $multi = !empty($options[$option]['multi']);

                $isFlag = $options[$option]['flag'] === true;

                if (!$isFlag && !isset($match['value'])) {
                    throw CliException::becauseOptionRequiresValue($option);
                }

                $value = isset($match['value']) ? $match['value'] : true;

                if ($multi && empty($parsedOptions[$option])) {
                    $parsedOptions[$option] = [];
                }

                $optionValue = $multi ? array_merge($parsedOptions[$option], [$value]) : $value;
                $parsedOptions[$option] = $optionValue;
                if (!empty($altName)) {
                    $parsedOptions[$altName] = $optionValue;
                }
                continue;
            }

            // Parse an argument.
            $argName = $args[$argsIndex]['name'];
            if (empty($args[$argsIndex]['multi'])) {
                // Move to the next argument only if this argument does not support multiple values.
                $argsIndex++;
                $parsedArgs[$argName] = $input[$i];
            } else {
                if (empty($parsedArgs[$argName])) {
                    $parsedArgs[$argName] = [];
                }
                $parsedArgs[$argName][] = $input[$i];
            }
        }

        $requiredArgs = array_column(array_filter($args, static function (array $argDef) {
            return $argDef['optional'] === false;
        }), 'name');

        if (empty($parsedOptions['help'])) {
            foreach ($requiredArgs as $requiredArg) {
                if (empty($parsedArgs[$requiredArg])) {
                    throw CliException::becauseRequiredArgIsMissing($requiredArg);
                }
            }
            foreach ($parsedOptions as $key => $value) {
                if ($options[$key]['flag'] === true && !is_bool($value)) {
                    throw CliException::becauseOptionDoesNotAcceptValue($requiredArg);
                }
            }
        }

        if (!isset($help['help'])) {
            $help['help'] = 'Display the command help.';
        }

        $map = array_merge($parsedArgs, $parsedOptions, [
            '_help' => $this->getHelp($this->name, $this->definition, $help),
            '_parsed' => [
                'options' => $parsedOptions,
                'args' => $parsedArgs
            ]
        ]);

        return new Map($map);
    }

    /**
     * {@inheritDoc}
     */
    public function getHelp()
    {
        $template = <<< HELP

%s

Signature: <green>%s</green>

Arguments:

  %s
  
Options:

  %s
  
HELP;

        $name = $this->name;
        $definition = $this->name . ' ' . implode(' ', array_keys($this->definition));

        if (!(count($this->args) || count($this->options))) {
            return "{$name}\n\nThis command has no arguments and no options.";
        }

        $signatures = array_column(array_merge($this->args, $this->options), 'signature');
        $pad = max(20, ...array_map('strlen', $signatures))+6;

        $argsList = count($this->args) ?
            $this->getArgsHelp($pad)
            : 'This command does not support any argument.';
        $optionsList = count($this->options) ?
            $this->getOptionsHelp($pad)
            : 'This command does not support any option.';

        $output = sprintf(
            $template,
            $name,
            $definition,
            $argsList,
            $optionsList
        );

        return $this->style($output) . PHP_EOL;
    }

    /**
     * Returns the help output for the command arguments.
     *
     * @param int $pad The padding value for the help output.
     *
     * @return string The help output for the command arguments.
     */
    protected function getArgsHelp($pad)
    {
        return implode("\n  ", array_map(function ($arg) use ($pad) {
            $comment = $this->definition[$arg['signature']];
            $signature = trim($arg['signature'], '[]*');
            $paddedName = $this->style('<green>' . str_pad($signature, $pad) . '</green>');
            $notes = implode(', ', array_keys(array_filter([
                'optional' => empty($arg['required']),
                '0-n values' => empty($arg['required']) && !empty($arg['multi']),
                '1-n values' => !empty($arg['required']) && !empty($arg['multi'])
            ])));
            return sprintf('%s%s%s', $paddedName, ($notes ? "({$notes}) " : ''), $comment);
        }, $this->args));
    }

    /**
     * Returns the help output for the command options.
     *
     * @param int $pad The padding value for the help output.
     *
     * @return string The help output for the command options.
     */
    protected function getOptionsHelp($pad)
    {
        return implode("\n  ", array_map(function (array $option) use ($pad) {
            $comment = $this->definition[$option['signature']];
            $signature = trim($option['signature'], '[]=*');
            $paddedName = $this->style('<green>' . str_pad($signature, $pad) . '</green>');
            $notes = implode(', ', array_keys(array_filter([
                'req. value' => empty($option['flag']),
                '0-n' => !empty($option['multi'])
            ])));
            return sprintf('%s%s%s', $paddedName, ($notes ? "({$notes}) " : ''), $comment);
        }, $this->options));
    }

    /**
     * Returns the current command name.
     *
     * @return string The current command name.
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Returns the command description.
     *
     * @return string The command description.
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * {@inheritDoc}
     */
    public function printHelp()
    {
        $this->output($this->getHelp());
    }
}
