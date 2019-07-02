<?php

use tad\WPBrowser\Traits\WithSqliteDatabase;

class DatabaseSnapshotsTest extends \Codeception\TestCase\WPTestCase
{
    use WithSqliteDatabase;

    protected $case = 'DatabaseSnapshotsTest';

    /**
     * It should allow taking a database snapshot
     *
     * @test
     */
    public function should_allow_taking_a_database_snapshot()
    {
        $method = 'should_allow_taking_a_database_snapshot';

        $this->snapshotSqliteDatabase();

        $this->assertFileExists(__DIR__ . "/__db_snapshots__/{$this->case}__{$method}__0.sqlite");

        $this->snapshotSqliteDatabase();

        $this->assertFileExists(__DIR__ . "/__db_snapshots__/{$this->case}__{$method}__1.sqlite");
    }

    public function arrayDataSet()
    {
        return [
            [0],
            [1],
            [2]
        ];
    }

    /**
     * It should correctly take database snapshots when using numbered data providers
     *
     * @test
     * @dataProvider arrayDataSet
     */
    public function should_correctly_take_snapshots_when_using_numbered_data_providers($index)
    {
        $method = 'should_correctly_take_snapshots_when_using_numbered_data_providers';

        $this->snapshotSqliteDatabase();

        $this->assertFileExists(__DIR__ . "/__db_snapshots__/{$this->case}__{$method}__{$index}__0.sqlite");

        $this->snapshotSqliteDatabase();

        $this->assertFileExists(__DIR__ . "/__db_snapshots__/{$this->case}__{$method}__{$index}__1.sqlite");
    }

    public function associativeDataSet()
    {
        return [
            'one' => ['one'],
            'two' => ['two'],
            'three' => ['two'],
        ];
    }

    /**
     * It should correctly take database snapshots when using associative data providers
     *
     * @test
     * @dataProvider associativeDataSet
     */
    public function should_correctly_take_snapshots_when_using_associative_data_providers($index)
    {
        $method = 'should_correctly_take_snapshots_when_using_associative_data_providers';

        $this->snapshotSqliteDatabase();

        $this->assertFileExists(__DIR__ . "/__db_snapshots__/{$this->case}__{$method}__{$index}__0.sqlite");

        $this->snapshotSqliteDatabase();

        $this->assertFileExists(__DIR__ . "/__db_snapshots__/{$this->case}__{$method}__{$index}__1.sqlite");
    }

    /**
     * It should allow creating named snapshots
     *
     * @test
     */
    public function should_allow_creating_named_snapshots()
    {
        $this->snapshotSqliteDatabase('/sub/test-1');

        $this->assertFileExists(__DIR__ . '/__db_snapshots__/sub/test-1.sqlite');

        $this->snapshotSqliteDatabase('test-2.sqlite');

        $this->assertFileExists(__DIR__ . "/__db_snapshots__/test-2.sqlite");

        static::factory()->post->create(['post_title' => 'test-post']);

        $this->snapshotSqliteDatabase('sub/test-1.sqlite');

        $this->assertFileExists(__DIR__ . "/__db_snapshots__/sub/test-1.sqlite");
    }

    /**
     * It should allow loading a db snapshot
     *
     * @test
     */
    public function should_allow_loading_a_db_snapshot()
    {
        $this->assertEmpty(get_page_by_title('test-post', 'post'));

        $this->loadSqliteSnapshot('sub/test-1.sqlite');

        $this->assertInstanceOf(WP_Post::class, get_page_by_title('test-post', OBJECT, 'post'));
    }

    /**
     * It should dump to file
     *
     * @test
     */
    public function should_dump_to_file()
    {
        $post_id = static::factory()->post->create(['post_title'=>"It's not you, it's me..."]);
        $this->assertInstanceOf(WP_Post::class, get_post($post_id));
        $file = __DIR__ . '/dump.sqlite';
    }
}
