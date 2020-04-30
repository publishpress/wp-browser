<?php
/**
 * Provides methods to output from a CLI command or application.
 *
 * @package lucatume\Cli\Traits
 */

namespace lucatume\Cli\Traits;

use lucatume\Cli\CliException;

/**
 * Trait WithCliOutput
 *
 * @package lucatume\Cli\Traits
 */
trait WithCliOutput
{
    /**
     * A map of each style supported by the trait.
     *
     * @var array<string,string>
     */
    protected static $stylesMap = [
        'reset' => "\e[0m",
        'bold' => "\e[1m",
        'reset_bold' => "\e[21m",
        'dim' => "\e[2m",
        'reset_dim' => "\e[22m",
        'underlined' => "\e[4m",
        'reset_underlined' => "\e[24m",
        'blink' => "\e[5m",
        'reset_blink' => "\e[25m",
        'reverse' => "\e[7m",
        'reset_reverse' => "\e[27m",
        'hidden' => "\e[8m",
        'reset_hidden' => "\e[28m",
        'default' => "\e[39m",
        'black' => "\e[30m",
        'red' => "\e[31m",
        'green' => "\e[32m",
        'yellow' => "\e[33m",
        'blue' => "\e[34m",
        'magenta' => "\e[35m",
        'cyan' => "\e[36m",
        'light_gray' => "\e[37m",
        'dark_gray' => "\e[90m",
        'light_red' => "\e[91m",
        'light_green' => "\e[92m",
        'light_yellow' => "\e[93m",
        'light_blue' => "\e[94m",
        'light_magenta' => "\e[95m",
        'light_cyan' => "\e[96m",
        'white' => "\e[97m",
        'bg_default' => "\e[49m",
        'bg_black' => "\e[40m",
        'bg_red' => "\e[[41m",
        'bg_green' => "\e[42m",
        'bg_yellow' => "\e[43m",
        'bg_blue' => "\e[44m",
        'bg_magenta' => "\e[45m",
        'bg_cyan' => "\e[46m",
        'bg_light_gray' => "\e[47m",
        'bg_dark_gray' => "\e[100m",
        'bg_light_red' => "\e[[101m",
        'bg_light_green' => "\e[102m",
        'bg_light_yellow' => "\e[103m",
        'bg_light_blue' => "\e[104m",
        'bg_light_magenta' => "\e[105m",
        'bg_light_cyan' => "\e[106m",
        'bg_light_white' => "\e[107m"
    ];

    /**
     * A list of extra/meta styles registered on/by the current trait user.
     *
     * @var array<string,array>
     */
    protected $styles = [];

    /**
     * The current output handler.
     * The callback function will receive, as first argument, the output string to print and, as second argument, the
     * type of output it's printing.
     *
     * @var callable
     */
    protected $outputHandler;

    /**
     * Returns the current output handler.
     *
     * @return callable The current output handler.
     */
    public function getOutputHandler()
    {
        return $this->outputHandler;
    }

    /**
     * Sets the application output handler.
     *
     * This will, implicitly, set the output handler for all the application commands.
     *
     * @param callable $outputHandler The output handler the application should use.
     */
    public function setOutputHandler(callable $outputHandler)
    {
        $this->outputHandler = $outputHandler;
    }

    /**
     * Returns the default output handler.
     *
     * @return callable The default output handler.
     */
    public function getDefaultOutputHandler()
    {
        return function ($text) {
            echo $this->style($text);
        };
    }

    /**
     * Styles the output using the styles provided by this trait.
     *
     * @param string $text The text to style.
     *
     * @return string The styled string.
     */
    protected function style($text)
    {
        $openStyles = [];
        $stylesMap = $this->getStylesMap();
        $replace = function (array $matches) use (&$openStyles, $stylesMap) {
            $style = $matches['style'];

            if (!isset($stylesMap[$style])) {
                throw CliException::becauseTheStyleIsNotSupported($style);
            }

            $close = !empty($matches['close']);

            if (empty($close)) {
                $openStyles[] = $style;
                return $stylesMap[$style];
            }

            if ($style !== end($openStyles)) {
                throw CliException::becauseTheClosingStyleIsNotOpen($style);
            }

            if (count($openStyles) === 1) {
                // Close the style only if this is the last style in the stack of open styles.
                return static::$stylesMap['reset'];
            }

            array_pop($openStyles);
            // Close the current style and re-open the other ones.
            return static::$stylesMap['reset']
                . implode('', array_map(static function ($style) use ($openStyles, $stylesMap) {
                        return $stylesMap[$style];
                }, $openStyles));
        };

        $styled = preg_replace_callback('/<(?<close>\\/)*(?<style>[^>]+?)>/', $replace, $text);

        return preg_match('/' . preg_quote(static::$stylesMap['reset'], '/') . '$/', $styled) ?
            $styled
            : $styled . static::$stylesMap['reset'];
    }

    /**
     * Outputs a message.
     *
     * @param string $message The message to output.
     * @param string $type The type of output, one of the `OUTPUT_` constants.
     */
    protected function output($message, $type = null)
    {
        call_user_func($this->outputHandler, $message, $type ?: static::OUTPUT_DEFAULT);
    }

    /**
     * Registers an extra style.
     *
     * @param $key
     * @param array<string> $styles The styles the extra style should apply.
     */
    public function registerStyle($key, array $styles)
    {
        $styleCodes = array_map(static function ($styleName) {
            if (!isset(static::$stylesMap[$styleName])) {
                throw CliException::becauseTheStyleIsNotSupported($styleName);
            }
            return preg_replace('#\\\\e\\[(\\d+)m#', '$1', static::$stylesMap[$styleName]);
        }, $styles);

        $styleString = '\e[' . implode(';', $styleCodes) . 'm';
        $this->styles[$key]  = $styleString;
    }

    /**
     * Registers a set of extra styles.
     *
     * @param array<string,array> $styles A map of each style to register. The format is the one used by the
     *                                    `registerStyle` method.
     *
     * @see WithCliOutput::registerStyle() for the format to use to define the styles to register.
     */
    public function registerStyles(array $styles)
    {
        foreach ($styles as $key => $value) {
            $this->registerStyle($key, $value);
        }
    }

    /**
     * Returns the complete map of the currently registered styles.
     *
     * @return array<string,string> The map of the currently registered styles. The key is the name of the style, the
     *                              value is the string that will produce the style output.
     */
    public function getStylesMap()
    {
        return array_merge(static::$stylesMap, $this->styles);
    }
}
