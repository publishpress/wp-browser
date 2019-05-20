<?php namespace tad\WPBrowser\Events;

class FiltersTest extends \Codeception\Test\Unit
{

    /**
     * @var \UnitTester
     */
    protected $tester;

    public function testSomeFeature()
    {
    }

    /**
     * It should allow adding a filter
     *
     * @test
     */
    public function should_allow_adding_a_filter()
    {
        Filters::addFilter('test', static function ($value) {
            return $value + 66;
        });

        $this->assertEquals(89, Filters::applyFilters('test', 23));
    }

    /**
     * It should allow stopping a filter propagation
     *
     * @test
     */
    public function should_allow_stopping_a_filter_propagation()
    {
        Filters::addFilter('test', static function ($value) {
            Filters::getCurrentEvent()->stopPropagation();
            return $value + 66;
        });
        Filters::addFilter('test', function ($value) {
            $this->fail('This should never apply');
        });

        $this->assertEquals(89, Filters::applyFilters('test', 23));
    }

    /**
     * It should apply filters according to priority and add order
     *
     * @test
     */
    public function should_apply_filters_according_to_priority_and_add_order()
    {
        Filters::addFilter('test', static function ($value) {
            Filters::getCurrentEvent()->stopPropagation();
            return $value + 66;
        }, 5);
        Filters::addFilter('test', function ($value) {
            $this->fail('This should never apply');
        }, 3);
        Filters::addFilter('test', function ($value) {
            $this->fail('This should never apply');
        }, 4);

        $this->assertEquals(89, Filters::applyFilters('test', 23));
    }

    /**
     * It should allow adding actions
     *
     * @test
     */
    public function should_allow_adding_actions()
    {
        $fired = false;
        Filters::addAction('test', static function () use (&$fired) {
            $fired = true;
        });

        Filters::doAction('test');

        $this->assertTrue($fired);
    }

    /**
     * It should apply actions in order
     *
     * @test
     */
    public function should_apply_actions_in_order()
    {
        $buffer = 0;
        Filters::addAction('test', function () use (&$buffer) {
            $buffer = 3;
        }, 5);
        Filters::addAction('test', function () use (&$buffer) {
            $this->assertEquals(3, $buffer);
            $buffer *= 4;
        }, 5);
        Filters::addAction('test', function () use (&$buffer) {
            $this->assertEquals(12, $buffer);
            $buffer /= 6;
        }, 2);

        Filters::doAction('test');
    }

    protected function _before()
    {
        Filters::removeAllFilters();
    }
}
