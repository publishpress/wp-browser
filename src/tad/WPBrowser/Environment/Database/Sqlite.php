<?php
/**
 * An extensions of the Sqlite Codeception driver to add some WordPress specific methods.
 *
 * @package tad\WPBrowser\Environment\Database
 */

namespace tad\WPBrowser\Environment\Database;

use Codeception\Lib\Driver\Db;
use Codeception\Util\ReflectionHelper;
use PDO;
use tad\WPBrowser\Environment\Installation;
use tad\WPBrowser\Exceptions\SQLiteException;
use tad\WPBrowser\Interfaces\WordPressDatabaseInterface;
use WP_SQLite_DB\PDOEngine;

/**
 * Class Sqlite
 *
 * @package tad\WPBrowser\Environment\Database
 */
class Sqlite extends \Codeception\Lib\Driver\Sqlite implements WordPressDatabaseInterface
{
    const DIR_MODE = 0704;

    /**
     * Keeps track of the current dump counter value on a class level.
     *
     * @var int
     */
    protected static $dumpCounter = 0;

    /**
     * The absolute path to WordPress root directory.
     *
     * @var string
     */
    protected $wpRootDir;

    /**
     * The database table prefix.
     *
     * @var string
     */
    protected $tablePrefix = 'wp_';

    /**
     * The database charset.
     *
     * @var string
     */
    protected $charset = 'utf8';

    /**
     * The database collation.
     *
     * @var string
     */
    protected $collate = '';

    /**
     * This database installation.
     *
     * @var Installation
     */
    protected $installation;
    /**
     * Whether the database is an in-memory one or not.
     *
     * @var bool
     */
    protected $inMemory;

    /** @noinspection MagicMethodsValidityInspection */
    /**
     * {@inheritDoc}
     */
    public function __construct($dsn, $user, $password, $options = null, Installation $installation = null)
    {
        $filename = substr($dsn, 7);

        if ($filename === ':memory:') {
            $this->inMemory = true;
        }

        if ($installation !== null) {
            $this->setInstallation($installation);
        }

        $this->filename = $this->inMemory ? ':memory:' : $this->wpRootDir . $filename;

        if (!empty($filename)) {
            $dsn = 'sqlite:' . $this->filename;
            Db::__construct($dsn, $user, $password, $options);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function setInstallation(Installation $installation)
    {
        $this->installation = $installation;
        $this->wpRootDir = $installation->getRootDir();

        $dbDropIn = $this->getDropinFilePath();

        $wpContent = dirname($dbDropIn);
        if (!is_dir($wpContent) && !mkdir($wpContent, static::DIR_MODE, true) && !is_dir($wpContent)) {
            throw SQLiteException::becauseDropinDirectoryCreationFailed($wpContent);
        }

        if (!file_exists($dbDropIn)) {
            copy(wpbrowser_includes_dir('/sqlite-db.php'), $dbDropIn);
        }

        if (!file_exists($dbDropIn)) {
            throw SQLiteException::becauseDbDropinCopyFailed($dbDropIn);
        }

        return $this;
    }

    /**
     * Returns the absolute path to the database drop-in file in the installation wp-content directory, if any.
     *
     * @return string|null The absolute path to the installation database drop-in file or `null` if the installation is
     *                     not set.
     */
    public function getDropinFilePath()
    {
        return $this->installation instanceof Installation ?
            $this->installation->getWpContentDir('db.php')
            : null;
    }

    /**
     * Builds and returns an instance of the database from a file.
     *
     * @param string $file The absolute path to the database file.
     *
     * @return Sqlite The built instance.
     * @throws SQLiteException If there are any issues while building the SQLite database instance.
     */
    public static function fromFile($file)
    {
        return new static("sqlite:{$file}", '', '');
    }

    /**
     * Builds and returns an instance of the
     *
     * @return Sqlite
     * @since TBD
     */
    public static function fromGlobalWpdb()
    {
        global $wpdb;
        /** @var \WP_SQLite_DB\wpsqlitedb $pdoEngine */
        $pdoEngine = ReflectionHelper::readPrivateProperty($wpdb, 'dbh');
        $instance = new static('', '', '');
        if ($pdoEngine instanceof PDOEngine) {
            $pdoEngine = new PDOEngine();
        }

        $instance->dbh = $GLOBALS['@pdo'];

        return $instance;
    }

    public static function dumpFromTo(Sqlite $source, Sqlite $destination)
    {
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
        return '';
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
        return $this->collate;
    }

    /**
     * {@inheritDoc}
     */
    public function getExtraPhp()
    {
        $dbDir = dirname($this->filename);
        $dbFile = basename($this->filename);

        return implode(PHP_EOL, [
            "define('DB_DIR', '{$dbDir}');",
            "define('DB_FILE', '{$dbFile}');"
        ]);
    }

    /**
     * {@inheritDoc}
     */
    public function shouldSkipCheck()
    {
        return true;
    }

    /**
     * Overrides the base implementation to avoid calling methods on a `null` `dbh` property.
     */
    public function __destruct()
    {
        if ($this->dbh === null) {
            return;
        }
        parent::__destruct();
    }

    /**
     * Resets the installation this database is for and removes the SQLite database drop-in from the installation if
     * required.
     *
     * @param bool $removeDropIn Whether to remove the databse drop-in file from the installation wp-content directory
     *                           or not.
     *
     * @throws SQLiteException If the deletion of the SQLite database drop-in file fails.
     */
    public function resetInstallation($removeDropIn = true)
    {
        if ($removeDropIn) {
            $this->removeDropinFile();
        }
        $this->installation = null;
    }

    /**
     * Removes the SQLite drop-in file from the installation wp-content directory.
     *
     * @return bool Whether the drop-in file existed and was removed from the installation wp-content directory or not.
     * @throws SQLiteException
     */
    public function removeDropinFile()
    {
        $dropinFile = $this->getDropinFilePath();

        if (!file_exists($dropinFile)) {
            return false;
        }

        $removed = unlink($dropinFile);
        if ($removed === false) {
            throw SQLiteException::becauseDbDropinDeletionFailed($dropinFile);
        }

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function dumpTo($destination)
    {
        if (file_exists($destination)) {
            $deleted = unlink($destination);

            if (!$deleted) {
                throw SQLiteException::becauseDatabaseFileDeletionFailed($destination);
            }
        }

        $destinationDb = new \SQLite3($destination);

        $creationSqlStmt = $this->dbh->query("SELECT sql FROM sqlite_master WHERE name NOT LIKE 'sqlite_%'");
        $creationSql = $creationSqlStmt->fetchAll(PDO::FETCH_COLUMN, 'sql');

        if (count($creationSql) === 0) {
            codecept_debug('SQLite database appears to be empty, no dump generated.');
            return false;
        }

        $implodedCreationSql = implode(";\n", $creationSql) . ';';
        $created = $destinationDb->exec($implodedCreationSql);

        if ($created === false) {
            throw SQLiteException::becauseQueryFailed($implodedCreationSql, $destinationDb->lastErrorMsg());
        }

        $destinationDb->close();
        $dump = 'dump_' . static::$dumpCounter++;

        $attachQuery = "ATTACH '{$destination}' AS {$dump}";
        $attached = $this->dbh->exec($attachQuery);

        if ($attached === false) {
            $error = $this->dbh->errorInfo();
            throw SQLiteException::becauseQueryFailed($attachQuery, end($error));
        }

        $dbList = $this->dbh->query('pragma database_list');
        if (count($dbList->fetchAll()) !== 2) {
            throw SQLiteException::becauseFileAttachmentFailed($destination);
        }

        $tablesListQuery = "SELECT name FROM sqlite_master 
            WHERE type='table' 
            AND NAME NOT LIKE 'sqlite_%';";
        $tablesList = $this->dbh->query($tablesListQuery);

        if ($tablesList === false) {
            $error = $this->dbh->errorInfo();
            throw SQLiteException::becauseQueryFailed($tablesListQuery, end($error));
        }

        $tables = $tablesList->fetchAll(PDO::FETCH_COLUMN, 0);
        $tablesPopulatedQuery = implode(";\n", array_map(static function ($table) use ($dump) {
                return "INSERT INTO {$dump}.{$table} SELECT * FROM main.{$table}";
        }, $tables)) . ';';

        $tablesPopulated = $this->dbh->exec($tablesPopulatedQuery);

        if ($tablesPopulated === false) {
            $error = $this->dbh->errorInfo();
            throw SQLiteException::becauseQueryFailed($tablesPopulatedQuery, end($error));
        }

        $detached = $this->dbh->exec("DETACH DATABASE {$dump}");

        if ($detached === false) {
            $error = $this->dbh->errorInfo();
            throw SQLiteException::becauseFileDetachmentFailed(end($error));
        }

        codecept_debug("SQLite database dump generated [{$destination}]");

        return true;
    }

    /**
     * Creates the database if required.
     *
     * @param bool $force Whether to force the creation of the database, that might cause the loss of its previous
     *                    version, or not.
     *
     * @return Sqlite The object.
     *
     * @throws SQLiteException If the database file cannot be created.
     */
    public function createDatabase($force = false)
    {
        if ($this->inMemory) {
            return $this;
        }

        if (!$force && file_exists($this->filename)) {
            return $this;
        }

        $dir = dirname($this->filename);

        if (!is_dir($dir) && !mkdir($dir, static::DIR_MODE, true) && !is_dir($dir)) {
            throw SQLiteException::becauseDatabaseDirectoryCreationFailed($dir);
        }

        if ($force && file_exists($this->filename)) {
            $removed = unlink($this->filename);
            if (!$removed) {
                throw SQLiteException::becauseDatabaseFileDeletionFailed($this->filename);
            }
        }

        $created = touch($this->filename);

        if (!$created || !file_exists($this->filename)) {
            throw SQLiteException::becauseDatabaseFileCreationFailed($this->filename);
        }

        return $this;
    }

    /**
     * Return the absolute path to the Sqlite file.
     *
     * @return string The absolute path to the Sqlite file.
     */
    public function getName()
    {
        return $this->filename;
    }

    /**
     * Returns whether the current database is an in-memory one or not.
     *
     * @return bool Whether the current database is an in-memory one or not.
     */
    public function isInMemory()
    {
        return $this->inMemory;
    }
}
