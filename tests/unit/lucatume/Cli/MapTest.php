<?php namespace lucatume\Cli;

class MapTest extends \Codeception\Test\Unit
{
    /**
     * It should allow setting and getting a value
     *
     * @test
     */
    public function should_allow_setting_and_getting_a_value()
    {
        $map = new Map(['foo' => 'bar', 'baz' => 23]);

        $this->assertEquals(23, $map('baz'));
        $this->assertEquals('bar', $map['foo']);
    }

    /**
     * It should allow defaulting the return value
     *
     * @test
     */
    public function should_allow_defaulting_the_return_value()
    {
        $map = new Map(['foo' => 'bar']);

        $this->assertEquals(null, $map['lorem']);
        $this->assertEquals(89, $map('lorem', 89));
    }

    /**
     * It should allow aliasing keys
     *
     * @test
     */
    public function should_allow_aliasing_keys()
    {
        $map = new Map(['foo' => 'bar'], ['lorem' => 'foo']);

        $this->assertEquals('bar', $map('foo', 89));
        $this->assertEquals('bar', $map('lorem', 89));
        $this->assertEquals('bar', $map['foo']);
        $this->assertEquals('bar', $map['lorem']);
    }

    /**
     * It should not allow setting keys
     *
     * @test
     */
    public function should_not_allow_setting_keys()
    {
        $map = new Map(['foo' => 'bar']);

        $this->expectException(\RuntimeException::class);

        $map['bar'] = 23;
    }

    /**
     * It should allow setting keys and returning a new Map
     *
     * @test
     */
    public function should_allow_setting_keys_and_returning_a_new_map()
    {
        $map = new Map(['foo' => 'bar']);

        $newMap = $map->update(['test' => 23]);

        $this->assertNotSame($map, $newMap);
        $this->assertEquals(null, $map['test']);
        $this->assertEquals(23, $newMap['test']);
    }
}
