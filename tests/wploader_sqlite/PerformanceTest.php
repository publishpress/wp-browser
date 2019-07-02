<?php

class PerformanceTest extends \Codeception\TestCase\WPTestCase
{
    public function counts()
    {
        foreach (range(1, 100) as $i) {
            yield [$i];
        }
    }

    /**
     * Test performance
     *
     * @dataProvider counts
     */
    public function test_performance($i)
    {
        $ids = static::factory()->post->create_many($i);

        foreach ($ids as $id) {
            $this->assertInstanceOf(WP_Post::class, get_post($id));
        }
    }
}
