<?php
/**
 * An open version of Symfony\Component\Console\Input\StringInput to expose some properties.
 *
 * @package tad\WPBrowser\Console
 */

namespace tad\WPBrowser\Console;

use Symfony\Component\Console\Input\StringInput;

/**
 * Class OpenStringInput
 *
 * @package tad\WPBrowser\Console
 */
class OpenStringInput extends StringInput
{
    public function getTokens()
    {
        return $this->tokens;
    }
}
