<?php
/**
 * An extensions of the MySQL Codeception driver to add some WordPress specific methods.
 *
 * @package tad\WPBrowser\Environment\Database
 */

namespace tad\WPBrowser\Environment\Database;

use tad\WPBrowser\Environment\Installation;
use tad\WPBrowser\Exceptions\SQLiteException;
use tad\WPBrowser\Interfaces\WordPressDatabaseInterface;

/**
 * Class MySQL
 *
 * @package tad\WPBrowser\Environment\Database
 */
class MySQL extends \Codeception\Lib\Driver\MySql implements WordPressDatabaseInterface
{
    /**
     * The current database name.
     *
     * @var string
     */
    protected $name;

    /**
     * The current database host.
     *
     * @var string
     */
    protected $host;

    /**
     * The database table prefix.
     *
     * @var string
     */
    protected $tablePrefix;

    /**
     * The database charset.
     *
     * @var string
     */
    protected $charset;

    /**
     * The database collation.
     *
     * @var string
     */
    protected $collation;

    /**
     * This database installation.
     *
     * @var Installation
     */
    protected $installation;

    /**
     * {@inheritDoc}
     */
    public function __construct($dsn, $user, $password, $options = null)
    {
        parent::__construct($dsn, $user, $password, $options);
        $this->parseDsn();
    }

    /**
     * Parses the database dsn string to fetch name, host and charset.
     */
    protected function parseDsn()
    {
        if ($this->name !== null) {
            return;
        }

        // mysql:dbname=testdb;host=127.0.0.1;charset=utf8
        preg_match('/mysql:dbname=(?<found>[^=])+/', $this->dsn, $dbname);
        $this->name = $dbname['found'] ?: '';
        preg_match('/host=(?<found>[^;]+)/', $this->dsn, $host);
        $this->host = $host['found'] ?: '';
        preg_match('/charset=(?<found>[^;])/', $this->dsn, $charset);
        $this->charset = $charset['found'] ?: '';
    }

    /**
     * {@inheritDoc}
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * {@inheritDoc}
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * {@inheritDoc}
     */
    public function getPassword()
    {
        return $this->password;
    }

    /**
     * {@inheritDoc}
     */
    public function getHost()
    {
        return $this->host;
    }

    /**
     * {@inheritDoc}
     */
    public function getTablePrefix()
    {
        return $this->tablePrefix;
    }

    /**
     * {@inheritDoc}
     */
    public function getCharset()
    {
        return $this->charset;
    }

    /**
     * {@inheritDoc}
     */
    public function getCollation()
    {
        return $this->collation;
    }

    /**
     * {@inheritDoc}
     */
    public function getExtraPhp()
    {
        return '';
    }

    /**
     * {@inheritDoc}
     */
    public function shouldSkipCheck()
    {
        return false;
    }

    /**
     * Creates the database if required.
     *
     * @return WordPressDatabaseInterface The object.
     */
    public function createDatabase()
    {
        throw new \RuntimeException('Not implemented');
    }

    /**
     * {@inheritDoc}
     */
    public function setInstallation(Installation $installation)
    {
        $this->installation = $installation;
    }

    /**
     * Resets the installation the database is associated with.
     */
    public function resetInstallation()
    {
        $this->installation = null;
    }

    public function restore()
    {
        // TODO: Implement restore() method.
    }

    public function dumpTo($destination)
    {
        // TODO: Implement backup() method.
    }
}
