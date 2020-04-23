<?php
/**
 * The base class of a CLI application.
 *
 * @since TBD
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
    /**
     * Parses the current
     *
     * @since TBD
     *
     */
    public function parseOrHelp(array $input = null)
    {
        if ($input === null) {
            // If the input is not specified, then read input arguments from the script arguments.
            global $argv;
            $input = array_slice($argv, 1);
        }


    }
}
