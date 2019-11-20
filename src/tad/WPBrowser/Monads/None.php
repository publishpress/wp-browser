<?php
/**
 * An implementation of the None monad.
 *
 * @package tad\WPBrowser\Monads
 */

namespace tad\WPBrowser\Monads;

/**
 * Class None
 *
 * @package tad\WPBrowser\Monads
 */
class None extends Maybe
{
    /**
     * Constructor
     *
     * @param null $value Ignored
     */
    public function __construct($value = null)
    {
    }

    /**
     * Always return another instance of None
     *
     * @param mixed $value     Ignored
     * @param mixed $noneValue Ignored
     *
     * @return static
     */
    public static function create($value = null, $noneValue = null)
    {
        return new static();
    }

    /**
     * Always return another instance of None
     *
     * @param \Closure $function Ignored
     * @param array    $args     Ignored
     *
     * @return None
     */
    public function bind(\Closure $function, array $args = [])
    {
        return new static();
    }

    /**
     * You cannot get the value of a None
     *
     * @throw \RuntimeException
     */
    public function value()
    {
        throw new \RuntimeException('None has no value');
    }
}
