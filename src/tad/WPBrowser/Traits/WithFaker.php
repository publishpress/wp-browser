<?php
/**
 * Provides methods to fake values.
 *
 * @package tad\WPBrowser\Traits
 */

namespace tad\WPBrowser\Traits;

use Faker\Factory;

/**
 * Trait WithFaker
 *
 * @package tad\WPBrowser\Traits
 */
trait WithFaker
{

    /**
     * The faker instance.
     *
     * @var
     */
    protected $faker;

    /**
     * Initializes the faker instance.
     *
     * @since TBD
     */
    protected function setUpFaker($locale = null)
    {
        if ($this->faker !== null) {
            return;
        }

        if ($locale === null) {
            $locale = property_exists($this, 'locale') ? $this->locale : Factory::DEFAULT_LOCALE;
        }

        $this->faker = Factory::create($locale);
    }
}
