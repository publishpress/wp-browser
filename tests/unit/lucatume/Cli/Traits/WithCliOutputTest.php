<?php namespace lucatume\Cli\Traits;

use lucatume\Cli\CliException;
use tad\Codeception\SnapshotAssertions\SnapshotAssertions;

class WithCliOutputTest extends \Codeception\Test\Unit
{
    use WithCliOutput;
    use SnapshotAssertions;

    /**
     * Test string w/o styles
     */
    public function test_string_w_o_styles()
    {
        $this->assertMatchesStringSnapshot($this->style('normal text'));
    }

    public function stringWithOneStyleDataProvider()
    {
        $map = array_filter(static::$stylesMap, static function ($style) {
            return 0 !== strpos($style, 'reset');
        }, ARRAY_FILTER_USE_KEY);
        foreach (array_keys($map) as $style) {
            yield $style => [sprintf('Normal <%1$s>%2$s</%1$s> Normal', $style, ucwords($style))];
        }
    }

    /**
     * Test string w/ one style
     * @dataProvider stringWithOneStyleDataProvider
     */
    public function test_string_w_one_style($input)
    {
        $this->assertMatchesStringSnapshot($this->style($input));
    }

    /**
     * Test string with one nested style
     */
    public function test_string_with_one_nested_style()
    {
        $this->assertMatchesStringSnapshot($this->style('Normal <green>green <bold>green and bold</bold> green</green> normal'));
    }

    /**
     * Test with two consecutive color styles
     */
    public function test_with_two_consecutive_color_styles()
    {
        $this->assertMatchesStringSnapshot($this->style('Normal <green>green <yellow>yellow</yellow> green</green> normal'));
    }

    /**
     * Test with styles and dim
     */
    public function test_with_styles_and_dim()
    {
        $this->assertMatchesStringSnapshot($this->style('Normal <green>green <dim><yellow>yellow dim</yellow> green dim</dim> green</green> normal'));
    }

    /**
     * Test it throws if nested styles are not orderly closed
     */
    public function test_it_throws_if_nested_styles_are_not_orderly_closed()
    {
        $this->expectException(CliException::class);

        $this->style('Normal <green>green <yellow>yellow</green></yellow> normal');
    }

    public function customStylesDataProvider()
    {
        return [
            'warning' => [
                ['warning' => ['blink', 'bg_yellow']],
                'normal <warning>warning</warning> normal'
            ],
            'notice' => [
                ['notice' => ['red', 'dim', 'bg_yellow']],
                'normal <notice>notice</notice> normal'
            ],
            'warning_and_notice' => [
                [
                    'warning' => ['blink', 'bg_yellow'],
                    'notice' => ['red', 'dim', 'bg_yellow']
                ],
                'normal <warning> warning <notice>warning and notice</notice> notice</warning> normal'
            ],
            'warning_and_notice_w_bold' => [
                [
                    'warning' => ['blink', 'bg_yellow'],
                    'notice' => ['red', 'dim', 'bg_yellow']
                ],
                'normal <warning> warning <notice>warning and notice <bold>bold</bold></notice> notice</warning> normal'
            ]
        ];
    }

    /**
     * It should allow registering custom styles
     *
     * @test
     * @dataProvider customStylesDataProvider
     */
    public function should_allow_registering_custom_styles(array $styles, $input)
    {
        $this->registerStyles($styles);

        $this->assertMatchesStringSnapshot($this->style($input));
    }
}
