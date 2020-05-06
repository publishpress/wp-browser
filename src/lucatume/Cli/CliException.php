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

    /**
     * An exception caused by a missing required argument.
     *
     * @param string $arg The name of the required argument.
     *
     * @return static The built exception instance.
     */
    public static function becauseRequiredArgIsMissing($arg)
    {
        return new static("Argument '{$arg}' is required, use '--help' for more information.");
    }

    /**
     * An exception caused by the user passing a value to an option that does not accept any.
     *
     * @param string $option The name of the option.
     *
     * @return static The built exception instance.
     */
    public static function becauseOptionDoesNotAcceptValue($option)
    {
        return new static("Option '{$option}' does not accept values, use '--help' for more information.");
    }

    /**
     * An exception caused by the user not passing a value to an option that requires one.
     *
     * @param string $option The name of the option.
     *
     * @return static The built exception instance.
     */
    public static function becauseOptionRequiresValue($option)
    {
        return new static("Option '{$option}' requires a value, use '--help' for more information.");
    }

    /**
     * An exception caused by the user passing an incorrectly structured definition.
     *
     * @return static The built exception instance.
     */
    public static function becauseEachDefinitionEntryShouldBeAMapElement()
    {
        return new static('Each command definition entry should be in the format <definition> => <help>.');
    }

    /**
     * An exception caused by the user passing an argument or option that does not conform to any standard.
     *
     * @param string $entry The name of the malformed entry.
     *
     * @return static The built exception instance.
     */
    public static function becauseDefinitionEntryDoesNotMatchAnyPattern($entry)
    {
        return new static("Definition entry {$entry} does not match any pattern.");
    }

    /**
     * An exception caused by the user passing more than one argument that supports multiple arguments.
     *
     * @return static The built exception instance.
     */
    public static function becauseMultiArgumentsShouldBeLast()
    {
        return new static('Only one argument can support multiple values and it must be the last.');
    }

    /**
     * An exception caused by the user passing trying to style a string and closing a tag that was never opened.
     *
     * @param string $style The style that is currently being closed w/o ever being opened.
     *
     * @return static The built exception instance.
     */
    public static function becauseTheClosingStyleIsNotOpen($style)
    {
        return new static("Closing the {$style} style, but this style was never opened.");
    }

    /**
     * An exception caused by the user passing trying to style a string with a not supported style.
     *
     * @param string  $style The not supported style.
     *
     * @return static The built exception instance.
     */
    public static function becauseTheStyleIsNotSupported($style)
    {
        return new static("The {$style} style is not a supported one.");
    }

    /**
     * An exception caused by an argument not being of the expected type.
     *
     * @param string $argName The name of the argument that should be a string.
     * @param string|array<string> $type The argument expected type or types.
     *
     * @return static The built exception instance.
     */
    public static function becauseArgumentShouldBeType($argName, $type)
    {
        $types = implode(', ', (array)$type);
        return new static("The {$argName} argument should be of type {$types}.");
    }

    /**
     * An exception caused by the user trying to run a command that is not defined in an application.
     *
     * @param string $appName The application name.
     * @param string $command The name of the non-existing command.
     *
     * @return static The built exception instance.
     */
    public static function becauseTheCommandIsNotDefined($appName, $command)
    {
        return new static("The {$appName} application does not have a {$command} command.");
    }
}
