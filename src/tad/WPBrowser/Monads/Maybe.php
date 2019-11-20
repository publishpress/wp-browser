<?php
/**
 * A less strict implementation of the Maybe monad to test against any empty value.
 *
 * @package tad\WPBrowser\Monads
 */

namespace tad\WPBrowser\Monads;

use Monad\Option;

/**
 * Class Maybe
 *
 * @package tad\WPBrowser\Monads
 */
class Maybe extends Option
{
    /**
     * {@inheritDoc}
     */
    public static function create($value = null, $noneValue = null)
    {
        return empty($value) || $value === $noneValue ? new None() : new Some($value);
    }

    /**
     * Calls a success or failure callable, depending on the value being empty or not.
     *
     * @param callable      $success The callable to call on success. It will receive the current value as input.
     * @param callable|null $failure The callable to call on failure. It will receive the current value as input.
     *
     * @return mixed The return value of the success or failure callback.
     */
    public function then(callable $success, callable $failure)
    {
        if (!empty($this->value)) {
            return $success($this->value);
        }

        return $failure($this->value);
    }
}
