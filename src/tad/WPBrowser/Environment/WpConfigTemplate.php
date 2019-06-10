<?php
/**
 * Handles the templating of a wp-config.php file.
 *
 * @package tad\WPBrowser\Environment
 */

namespace tad\WPBrowser\Environment;

use Codeception\Util\Template;

/**
 * Class WpConfigTemplate
 *
 * @package tad\WPBrowser\Environment
 */
class WpConfigTemplate extends Template
{

    /**
     * {@inheritDoc}
     */
    public function produce()
    {
        $this->template = file_get_contents(__DIR__ . '/wp-config.php.template');

        return parent::produce();
    }
}
