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
        'reset' => 0,
        'bold' => 1,
        'reset_bold' => 21,
        'dim' => 2,
        'reset_dim' => 22,
        'underlined' => 4,
        'reset_underlined' => 24,
        'blink' => 5,
        'reset_blink' => 25,
        'reverse' => 7,
        'reset_reverse' => 27,
        'hidden' => 8,
        'reset_hidden' => 28,
        'default' => 39,
        'black' => 30,
        'red' => 31,
        'green' => 32,
        'yellow' => 33,
        'blue' => 34,
        'magenta' => 35,
        'cyan' => 36,
        'light_gray' => 37,
        'dark_gray' => 90,
        'light_red' => 91,
        'light_green' => 92,
        'light_yellow' => 93,
        'light_blue' => 94,
        'light_magenta' => 95,
        'light_cyan' => 96,
        'white' => 97,
        'bg_default' => 49,
        'bg_black' => 40,
        'bg_red' => 41,
        'bg_green' => 42,
        'bg_yellow' => 43,
        'bg_blue' => 44,
        'bg_magenta' => 45,
        'bg_cyan' => 46,
        'bg_light_gray' => 47,
        'bg_dark_gray' => 100,
        'bg_light_red' => 101,
        'bg_light_green' => 102,
        'bg_light_yellow' => 103,
        'bg_light_blue' => 104,
        'bg_light_magenta' => 105,
        'bg_light_cyan' => 106,
        'bg_light_white' => 107
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
        return static function ($text) {
            echo $text;
        };
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
            return preg_replace('#\\\\033\\[(\\d+)m#', '$1', static::$stylesMap[$styleName]);
        }, $styles);

        $styleString = '\e[' . implode(';', $styleCodes) . 'm';
        $this->styles[$key] = $styleString;
    }

    /**
     * Prints a styled output string.
     *
     * @param string $text The styled output string to print.
     */
    public function styledOutput($text)
    {
        $this->output($this->style($text));
    }

    /**
     * Outputs a message.
     *
     * @param string $message The message to output.
     * @param string $type The type of output, one of the `OUTPUT_` constants.
     */
    public function output($message, $type = null)
    {
        call_user_func($this->outputHandler, $message, $type ?: 'default');
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
        $replace = static function (array $matches) use (&$openStyles, $stylesMap) {
            $style = $matches['style'];

            if (!isset($stylesMap[$style])) {
                throw CliException::becauseTheStyleIsNotSupported($style);
            }

            $close = !empty($matches['close']);

            if (empty($close)) {
                $openStyles[] = $style;
                return "\033[" . $stylesMap[$style] . "m";
            }

            if ($style !== end($openStyles)) {
                throw CliException::becauseTheClosingStyleIsNotOpen($style);
            }

            if (count($openStyles) === 1) {
                $openStyles = [];
                // Close the style only if this is the last style in the stack of open styles.
                return "\033[" . static::$stylesMap['reset'] . "m";
            }

            array_pop($openStyles);
            // Close the current style and re-open the other ones.
            return "\033[" . static::$stylesMap['reset'] . "m"
                . implode('', array_map(static function ($style) use ($openStyles, $stylesMap) {
                    return "\033[" . $stylesMap[$style] . "m";
                }, $openStyles));
        };

        $styled = preg_replace_callback('/<(?<close>\\/)*(?<style>[^>]+?)>/', $replace, $text);

        return preg_match('/' . preg_quote("\033[" . static::$stylesMap['reset'] . "m", '/') . '$/', $styled) ?
            $styled
            : $styled . "\033[" . static::$stylesMap['reset'] . "m";
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
