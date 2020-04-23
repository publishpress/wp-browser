<?php
/**
 * Models a map that contains a command parsed input arguments and options.
 *
 * @package lucatume\Cli
 */

namespace lucatume\Cli;

/**
 * Class Args
 *
 * @package lucatume\Cli
 */
class Args implements \ArrayAccess
{
    /**
     * The parsed arguments and options map.
     *
     * @var array<string,mixed>
     */
    protected $map = [];
    /**
     * A map relating aliases to their source keys.
     * @var array<string,string>
     */
    protected $aliases = [];

    /**
     * Args constructor.
     * @param array $map
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
        $offset = $this->redirectAlias($offset);
        return $this->map[$offset] = $value;
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
