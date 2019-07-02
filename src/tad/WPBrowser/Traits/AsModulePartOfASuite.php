<?php
/**
 * Provides common methods to modules part of a suite.
 *
 * @package tad\WPBrowser\Traits
 */

namespace tad\WPBrowser\Traits;

use Codeception\Lib\ModuleContainer;
use Codeception\Util\ReflectionHelper;

/**
 * Trait AsModulePartOfASuite
 *
 * @package tad\WPBrowser\Traits
 * @since   TBD
 */
trait AsModulePartOfASuite
{
    /**
     * Returns the name of the suite the module belongs to.
     *
     * @return string The name of the suite the module belongs to.
     */
    protected function getSuiteName()
    {
        if (!property_exists($this, 'moduleContainer') || !$this->moduleContainer instanceof ModuleContainer) {
            return 'suite';
        }
        try {
            $config = ReflectionHelper::readPrivateProperty($this->moduleContainer, 'config');
            if (!is_array($config) || empty($config['path'])) {
                return 'suite';
            }
            return basename($config['path']);
        } catch (\ReflectionException $e) {
            return 'suite';
        }
    }
}
