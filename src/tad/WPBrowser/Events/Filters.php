<?php
/**
 * Provides a static API to hook and act on wp-browser events.
 *
 * @package tad\WPBrowser\Events
 */

namespace tad\WPBrowser\Events;

use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Class Filters
 *
 * @package tad\WPBrowser\Events
 */
class Filters extends EventDispatcher
{

    /**
     * A singleton instance of this class.
     *
     * @var Filters
     */
    private static $instance;

    /**
     * The Event instance currently being dispatched.
     *
     * @var Event
     */
    private $currentEvent;

    /**
     * Returns the Event currently being dispatched, if any.
     *
     * Listeners can invoke this method while applying a filter to stop the event propagation and break out of the
     * propagation cycle.
     *
     * @return Event The event currently being dispatched, if any.
     */
    public static function getCurrentEvent()
    {
        return self::instance()->currentEvent;
    }

    /**
     * Returns, building it if not already built, the singleton instance used by the class.
     *
     * @return Filters The singleton instance of this class.
     */
    private static function instance()
    {
        if (static::$instance === null) {
            static::$instance = new static;
        }

        return static::$instance;
    }

    /**
     * Removes all the listeners from all the filters.
     */
    public static function removeAllFilters()
    {
        $instance = self::instance();
        foreach ($instance->getListeners() as $eventName => $listeners) {
            foreach ($listeners as $listener) {
                $instance->removeListener($eventName, $listener);
            }
        }
    }

    /**
     * Adds a listener on an action.
     *
     * Differently from WordPress implementation all listeners will be passed all available arguments.
     *
     * @param string $action The name of the action to watch for.
     * @param callable $listener The callable that will be called when the filter is applied.
     * @param int $priority The callback priority. Differently from WordPress implementation higher is applied
     *                              first; defaults to 0.
     */
    public static function addAction($action, $listener, $priority = 0)
    {
        self::addFilter($action, $listener, $priority);
    }

    /**
     * Adds a listener on a filter.
     *
     * Filters, much like in WordPress, will always provide the filtered value as first parameters.
     *
     * Any callable will be passed the filtered value as first argument and the rest of the filter data after it.
     * Differently from WordPress implementation all listeners will be passed all available arguments.
     *
     * @param string $tag The name of the event to filter.
     * @param callable $listener The callable that will be called when the filter is applied.
     * @param int $priority The callback priority. Differently from WordPress implementation higher is applied
     *                              first; defaults to 0.
     */
    public static function addFilter($tag, $listener, $priority = 0)
    {
        self::instance()->addListener($tag, $listener, (int)$priority);
    }

    /**
     * Fires an action passing the provided data to all listeners.
     *
     * Listeners  will be passed all arguments provided to the aciton, the first is always the filtered
     * value.
     *
     * @param string $action The name of the filter that is being applied.
     * @param mixed ...$data The rest of the arguments passed to the action.
     */
    public static function doAction($action, ...$data)
    {
        self::applyFilters($action, ...$data);
    }

    /**
     * Applies a filter by calling all the listeners attached to it.
     *
     * Listeners  will be passed all arguments provided to the filter, the first is always the filtered
     * value.
     *
     * @param string $tag The name of the filter that is being applied.
     * @param mixed $value The value currently being filtered.
     * @param mixed ...$data The rest of the arguments passed to the filter.
     *
     * @return mixed The filtered value.
     */
    public static function applyFilters($tag, $value = null, ...$data)
    {
        $event = new Event();
        self::instance()->currentEvent = $event;

        if ($listeners = self::instance()->getListeners($tag)) {
            foreach ($listeners as $listener) {
                if ($event->isPropagationStopped()) {
                    break;
                }
                $value = $listener($value, ...$data);
            }
        }

        return $value;
    }
}
