<?php
/**
 * Models a map.
 *
 * @package lucatume\Cli
 */

namespace lucatume\Cli;

/**
 * Class Map
 *
 * @package lucatume\Cli
 */
class Map implements \ArrayAccess
{
    /**
     * The parsed arguments and options map.
     *
     * @var array<string,mixed>
     */
    protected $map = [];

    /**
     * A map relating aliases to their source keys.
     *
     * @var array<string,string>
     */
    protected $aliases = [];

    /**
     * Args constructor.
     *
     * @param array<string,mixed> $map A map of data to hydrate the map with.
     * @param array<string,string> $aliases A map of aliases mapping aliases to their sources.
     */
    public function __construct(array $map = [], array $aliases = [])
    {
        $this->map = $map;
        $this->aliases = $aliases;
    }

    /**
     * {@inheritDoc}
     */
    public function offsetExists($offset)
    {
        return isset($this->map[$this->redirectAlias($offset)]);
    }

    /**
     * {@inheritDoc}
     */
    protected function redirectAlias($alias)
    {
        return isset($this->aliases[$alias]) ? $this->aliases[$alias] : $alias;
    }

    /**
     * {@inheritDoc}
     */
    public function offsetGet($offset)
    {
        $offset = $this->redirectAlias($offset);
        return isset($this->map[$offset]) ? $this->map[$offset] : null;
    }

    /**
     * {@inheritDoc}
     */
    public function offsetSet($offset, $value)
    {
        throw new \RuntimeException(
            'The map is immutable, use the update method to get an updated version of the Map.'
        );
    }

    /**
     * Returns a new instance of the map, modeling the updates.
     *
     * @param array<string,mixed> $updates The map updates.
     *
     * @return Map A new map, updated.
     */
    public function update(array $updates)
    {
        if (count(array_filter(array_keys($updates), 'is_string')) !== count($updates)) {
            throw new \InvalidArgumentException('Map updates must be provided with an associative array.');
        }
        foreach ($updates as $key => $value) {
            $offset = $this->redirectAlias($key);
            $alterations[$offset] = $value;
        }

        return new static(array_merge($this->map, $alterations), $this->aliases);
    }

    /**
     * {@inheritDoc}
     */
    public function offsetUnset($offset)
    {
        $offset = $this->redirectAlias($offset);
        unset($this->map[$offset]);
    }

    /**
     * Gets the value of an argument or option with a default value fallback.
     *
     * @param string $key The name of the argument or the name of the option, w/o the leading `-` or `--`, to get.
     * @param null|mixed $default The default value to return if the argument or option is not set.
     *
     * @return mixed|null The value of the argument or option, or the default value if not set.
     */
    public function __invoke($key, $default = null)
    {
        $key = $this->redirectAlias($key);
        return isset($this->map[$key]) ? $this->map[$key] : $default;
    }
}
