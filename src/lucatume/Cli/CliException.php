<?php
/**
 * An exception thrown in the context of a CLI command.
 *
 * @package lucatume\Cli
 */

namespace lucatume\Cli;

/**
 * Class CliException
 *
 * @package lucatume\Cli
 */
class CliException extends \Exception
{

    public static function becauseRequiredArgIsMissing($arg)
    {
        return new static("\n\e[31mArgument '{$arg}' is required, use '--help' for more information.\e[0m\n\n");
    }

    public static function becauseOptionDoesNotAcceptValue($option)
    {
        throw new static("\n\e[31mOption '{$option}' does not accept values, use '--help' for more information.\e0[m\n\n");
    }

    public static function becauseOptionRequiresValue($option)
    {
        throw new static("\n\e[31mOption '{$option}' requires a value, use '--help' for more information.\e[0m\n\n");
    }

    public static function becauseDefinitionIsEmpty()
    {
        throw new static("\n\e[31mCommands require a definition.\e[0m\n\n");
    }

    public static function becauseEachDefinitionEntryShouldBeAMapElement()
    {
        throw new static("\n\e[31mEach command definition entry should be in the format <definition> => <help>\e[0m\n\n");
    }

    public static function becauseDefinitionEntryDoesNotMatchAnyPattern($entry)
    {
        throw new static("\n\e[31mDefinition entry {$entry} does not match any pattern.\e[0m\n\n");
    }

    public static function becauseMultiArgumentsShouldBeLast()
    {
        throw new static("\n\e[31mOnly one argument can support multiple values and it must be the last.\e[0m\n\n");
    }
}
