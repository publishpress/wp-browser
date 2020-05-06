<?php
/**
 * The API implemented by anything that can provide help.
 *
 * @package lucatume\Cli\Interfaces
 */

namespace lucatume\Cli\Interfaces;

/**
 * Interface Helper
 *
 * @package lucatume\Cli\Interfaces
 */
interface Helper
{
    /**
     * Returns the help text.
     *
     * @return string The help text.
     */
    public function getHelp();

    /**
     * Outputs the help text.
     */
    public function printHelp();
}
