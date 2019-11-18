<?php
/**
 * Runs queries on a PDO connection with closure-based configuration.
 *
 * @package tad\WPBrowser\Services\Db
 */

namespace tad\WPBrowser\Services\Db;

class PDOQueryRunner
{
    const SUCCESS = '00000';

    /**
     * The current PDO connection instance.
     *
     * @var \PDO
     */
    protected $pdo;

    /**
     * PDOQueryRunner constructor.
     *
     * @param \PDO $pdo A PDO connection instance.
     */
    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Runs a query on a PDO connection with flow control via callbacks.
     *
     * @param \PDO     $db    The database connection handle.
     * @param string   $query The SQL query to run.
     * @param array    $args  An array of arguments for the query.
     * @param callable $retry The retry callback, this will be called if the query fails, within the limit of 3 retries.
     *                        This will receive a `PDO` instance and a `PDOStatement|null` as input and it's supposed to
     *                        return a `PDO` instance.
     * @param callable $fail  The fail callback, this will be called if the query fails, beyond the limit of 3 retries.
     *                        This will receive a `PDO` instance as input.
     *
     * @return \PDOStatement|false The query resulting statement, or `false` on failure.
     */
    public function run($query, array $args = [], \Closure $retry = null, \Closure $fail = null)
    {
        $retry = is_callable($retry) ? $retry : '\tad\WPBrowser\repeater';
        $fail = is_callable($fail) ? $fail : '\tad\WPBrowser\goOn';

        $retries = 1;
        do {
            $statement = $this->pdo->prepare($query);

            if (!$statement instanceof \PDOStatement) {
                $this->pdo = $retry($this->pdo, $statement);

                if (!$this->pdo instanceof \PDO) {
                    $fail($this->pdo);
                    return false;
                }

                $statement = $this->pdo->prepare($query);
            }

            $i = 0;
            foreach ($args as $value) {
                $i++;
                if (is_bool($value)) {
                    $type = \PDO::PARAM_BOOL;
                } elseif (is_int($value)) {
                    $type = \PDO::PARAM_INT;
                } else {
                    $type = \PDO::PARAM_STR;
                }
                $statement->bindValue($i, $value, $type);
            }

            $statement->execute();

            if ($statement->errorCode() !== static::SUCCESS) {
                $this->pdo = $retry($this->pdo, $statement);
            } else {
                return $statement;
            }
        } while (++$retries < 3);

        $fail($this->pdo, $statement);

        return false;
    }

    /**
     * Returns the last PDO error information, if any.
     *
     * @return array The last PDO error information, if any.
     */
    public function errorInfo()
    {
        return $this->pdo->errorInfo();
    }
}
