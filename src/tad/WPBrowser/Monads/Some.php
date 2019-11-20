<?php
/**
 * An extension of the base Some monad to build using the correct Maybe class.
 *
 * @package tad\WPBrowser\Monads
 */

namespace tad\WPBrowser\Monads;

/**
 * Class Some
 *
 * @package tad\WPBrowser\Monads
 */
class Some extends Maybe
{
    /**
     * Some constructor.
     *
     * @param mixed $value The value to build the monad on.
     */
    public function __construct($value)
    {
        $this->value = $value;
    }

    /**
     * {@inheritDoc}
     */
    public function bind(\Closure $function, array $args = [], $noneValue = null)
    {
        return Maybe::create($this->callFunction($function, $this->value, $args), $noneValue);
    }
}
