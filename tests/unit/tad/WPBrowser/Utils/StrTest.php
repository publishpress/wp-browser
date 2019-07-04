<?php namespace tad\WPBrowser\Utils;

class StrTest extends \Codeception\Test\Unit
{
    public function replaceRecursiveData()
    {
        return [
            'empty' => ['', ''],
            'null' => [null, null],
            'zero' => [0, 0],
            'false' => [false, false],
            'empty_array' => [[], []],
            'string_wo_needle' => ['foo', 'foo'],
            'string_w_needle' => ['foo bar', 'foo baz'],
            'array_w_needle' => [
                ['one' => 'foo', 'two' => 'bar', 'three' => ['some' => 'bar']],
                ['one' => 'foo', 'two' => 'baz', 'three' => ['some' => 'baz']],
            ],
        ];
    }

    /**
     * Test replaceRecursive
     *
     * @dataProvider replaceRecursiveData
     */
    public function test_replaceRecursive($subject, $expected, $search = 'bar', $replace = 'baz')
    {
        $this->assertEquals($expected, Str::replaceRecursive($search, $replace, $subject));
    }
}
