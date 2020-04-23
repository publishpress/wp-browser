<?php
/**
 * Models a command, part of a CLI Application.
 *
 * @package lucatume\Cli
 */

namespace lucatume\Cli;

/**
 * Class Command
 *
 * @package lucatume\Cli
 */
class Command
{
    use WithCliStyles;

    /**
     * The command name.
     *
     * @var string
     */
    protected $name;

    /**
     * The current handler of the application output.
     *
     * @var callable
     */
    protected $output;

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
     * Command constructor.
     *
     * @param string $name The command name.
     * @param array<string,string> $definition The map of the command definitions of arguments and options and the description.
     * @param array<string,string> $help The command help text for command argument or option.
     * @param callable|null $output The current output handler for the command. Echo if not provided.
     */
    public function __construct($name, array $definition, $output = null)
    {
        if (empty($definition)) {
            throw CliException::becauseDefinitionIsEmpty();
        }
        if (count(array_filter(array_keys($definition), 'is_string')) !== count($definition)) {
            throw CliException::becauseEachDefinitionEntryShouldBeAMapElement();
        }
        $this->name = $name;

        if (!isset($definition['[--help]'])) {
            $definition['[--help]'] = 'Display this help text';
        }

        $this->definition = $definition;

        $this->output = $output ?: function ($text) {
            echo $this->style($text);
        };
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

        foreach (array_keys($definition) as $entry) {
            if (preg_match('/^\\[--(?<option>[^=]+)(?<value>=)*](?<multi>\\*)*/', $entry, $m)) {
                // Matches [--quiet], [--file=] and [--config=]*
                $options[] = [
                    'signature' => $m[0],
                    'long' => $m['option'],
                    'short' => null,
                    'flag' => empty($m['value']),
                    'multi' => isset($m['multi'])
                ];
                continue;
            }

            if (preg_match('/^\\[-(?<option>[^=|]*)(?<value>=)*](?<multi>\\*)*/', $entry, $m)) {
                // Matches [-q], [-f=] and [-c=]*
                $options[] = [
                    'signature' => $m[0],
                    'long' => null,
                    'short' => $m['option'],
                    'flag' => empty($m['value']),
                    'multi' => isset($m['multi'])
                ];
                continue;
            }

            if (preg_match('/^\\[-(?<short>\\w)\\|--(?<long>[^=]+)(?<value>=)*](?<multi>\\*)*/us', $entry, $m)) {
                // Matches [-q|--quiet], [-f|--file=] and [-c|--config=]*
                $options[] = [
                    'signature' => $m[0],
                    'long' => $m['long'],
                    'short' => $m['short'],
                    'flag' => empty($m['value']),
                    'multi' => isset($m['multi'])
                ];

                continue;
            }

            if (preg_match('/^(?<optional>\\[)*(?<name>[^]*]+)]*(?<multi>\\*)*/us', $entry, $m)) {
                $args[] = [
                    'signature' => $m[0],
                    'name' => $m['name'],
                    'required' => empty($m['optional']),
                    'multi' => !empty($m['multi'])
                ];
            }
        }

        usort($options, static function (array $a, array $b) {
            return strcasecmp($a['signature'], $b['signature']);
        });

        $this->args = $args;
        $this->options = $options;
    }

    /**
     * Parses and validates the input arguments and options and returns a map of the validated argument and option
     * values.
     *
     * @param array<string>|null $input The current command input, or `null` to use the global script `$argv`.
     * @return Args The parsed arguments.
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

        $parsedOptions = [];
        $parsedArgs = [];
        $argsIndex = 0;
        for ($i = 0; $i < $argsCount; $i++) {
            if (preg_match('/^(--(?<long>[^=]+)|-(?<short>[^=]+))(=(?<value>.*))*$/usm', $input[$i], $match)) {
                $option = !empty($match['short']) ? $match['short'] : $match['long'];
                $altName = !empty($match['short']) ? $options[$option]['long'] : $options[$option]['short'];
                $multi = !empty($options[$option]['multi']);

                if ($options[$option]['flag'] === false && !isset($match['value'])) {
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
            } else {
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
        }

        $requiredArgs = array_column(array_filter($args, static function (array $argDef) {
            return $argDef['required'] === true;
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
            '_help' => $this->help($this->name, $this->definition, $help),
            '_parsed' => [
                'options' => $parsedOptions,
                'args' => $parsedArgs
            ]
        ]);

        return new Args($map);
    }

    /**
     * Returns the command help text.
     *
     * @return string The command help text.
     */
    public function help()
    {
        $template = <<< HELP

%s

Signature: \e[32m%s\e[0m

Arguments:
  %s
  
Options:
  %s
  
HELP;

        $args = $this->args;
        $options = $this->options;
        $name = $this->name;
        $definition = $this->name . ' ' . implode(' ', array_keys($this->definition));

        if (!(count($args) || count($options))) {
            return "{$name}\n\nThis command has no arguments and no options.";
        }

        $lengths = array_map('strlen', array_column(array_merge($args, $options), 'signature'));
        $pad = max(20, count($lengths) > 1 ? max(...$lengths) + 6 : reset($lengths) + 6);

        $output = sprintf(
            $template,
            $name,
            $definition,
            implode("\n  ", array_map(function ($arg) use ($pad) {
                $comment = $this->definition[$arg['signature']];
                $signature = trim($arg['signature'], '[]*');
                $paddedName = str_pad("\e[32m{$signature}\e[0m", $pad);
                $notes = implode(', ', array_keys(array_filter([
                    'optional' => empty($arg['required']),
                    '0-n values' => empty($arg['required']) && !empty($arg['multi']),
                    '1-n values' => !empty($arg['required']) && !empty($arg['multi'])
                ])));
                return sprintf('%s%s%s', $paddedName, ($notes ? "({$notes}) " : ''), $comment);
            }, $args)),
            implode("\n  ", array_map(function (array $option) use ($pad) {
                $comment = $this->definition[$option['signature']];
                $signature = trim($option['signature'], '[]=*');
                $paddedName = str_pad("\e[32m{$signature}\e[0m", $pad);
                $notes = implode(', ', array_keys(array_filter([
                    'req. value' => empty($option['flag']),
                    '0-n' => !empty($option['multi'])
                ])));
                return sprintf('%s%s%s', $paddedName, ($notes ? "({$notes}) " : ''), $comment);
            }, $options))
        );

        return $output . PHP_EOL;
    }
}
