<?php

use Dotenv\Environment\DotenvFactory;
use Dotenv\Loader;
use tad\WPBrowser\Services\Db\PDOQueryRunner;

/**
 *
 *
 * @since   TBD
 *
 * @package tad\WPBrowser\Services\Db
 */
class PDOQueryRunnerTest extends \Codeception\Test\Unit
{

    /**
     * It should correctly run a query w/o args
     *
     * @test
     */
    public function should_correctly_run_a_query_w_o_args()
    {
        $runner = new PDOQueryRunner($this->makePDO());
        $stmt = $runner->run('show databases');
        $this->assertInstanceOf(\PDOStatement::class, $stmt);
    }

    protected function makePDO()
    {
        $env = new Loader([codecept_root_dir('.env.testing')], new DotenvFactory());
        $db = new \PDO(
            sprintf('mysql:host=%s;dbname=test', $env->getEnvironmentVariable('DB_HOST')),
            $env->getEnvironmentVariable('DB_USER'),
            $env->getEnvironmentVariable('DB_PASSWORD')
        );
        $this->assertInstanceOf(\PDO::class, $db);
        return $db;
    }

    /**
     * It should correctly run a query w/ args
     *
     * @test
     */
    public function should_correctly_run_a_query_w_args()
    {
        $table = 'test_' . substr(md5(microtime(true)), 0, 8);

        $runner = new PDOQueryRunner($this->makePDO());

        $retry = $fail = static function (\PDO $pdo, PDOStatement $statement) {
            codecept_debug($statement->errorInfo());
            return $pdo;
        };

        $runner->run("create table {$table} (
            ID INT(8) NOT NULL AUTO_INCREMENT,
            ref_id INT(8) NOT NULL,
            meta_key VARCHAR(255),
            meta_value VARCHAR(255),
            KEY (ID)
        )", [], $retry, $fail);
        $stmt = $runner->run("select * from {$table} where ref_id = ?", [23], $retry, $fail);
        $this->assertInstanceOf(PDOStatement::class, $stmt, json_encode($runner->errorInfo(), JSON_PRETTY_PRINT));
        $this->assertCount(0, $stmt->fetchAll());
    }
}
