<?php
/**
 * Models a Sqlite installation database.
 *
 * @package tad\WPBrowser\Environment\Database
 */

namespace tad\WPBrowser\Environment\Database;

use tad\WPBrowser\Environment\Installation;

/**
 * Class Sqlite
 *
 * @package tad\WPBrowser\Environment\Database
 */
class Sqlite implements WordPressDatabaseInterface
{
    /**
     * The absolute path to WordPress root directory.
     *
     * @var string
     */
    protected $wpRootDir;

    /**
     * The name of the database file, w/o the path, that will be created in WordPress root directory.
     *
     * @var string
     */
    protected $fileName;

    /**
     * This database installation.
     *
     * @var Installation
     */
    protected $installation;

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
    protected $charset;

    /**
     * The database collation.
     *
     * @var string
     */
    protected $collate;

    /**
     * Sqlite constructor.
     *
     * @param string|null $fileName    The name, w/o the path, of the file that will be used for the database.
     * @param string      $tablePrefix The table prefix to use for the WordPress installation.
     * @param string      $charset     The database charachter set.
     * @param string      $collate     The database collation.
     */
    public function __construct($fileName = 'database.sqlite', $tablePrefix = 'wp_', $charset = 'utf8', $collate = '')
    {
        $this->fileName = trim($fileName, '\\/');
        $this->tablePrefix = $tablePrefix;
        $this->charset = $charset;
        $this->collate = $collate;
    }

    /**
     * {@inheritDoc}
     */
    public function getName()
    {
        return 'SQLITE';
    }

    /**
     * {@inheritDoc}
     *
     */
    public function getUser()
    {
        return 'SQLITE';
    }

    /**
     * {@inheritDoc}
     */
    public function getPassword()
    {
        return 'SQLITE';
    }

    /**
     * {@inheritDoc}
     */
    public function getHost()
    {
        return 'SQLITE';
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
        $dbDir = dirname($this->getFilePath());

        return implode(PHP_EOL, [
            "define('DB_DIR', '{$dbDir}');",
            "define('DB_FILE', '{$this->getFileName()}');"
        ]);
    }

    /**
     * Returns the absolute path to the Sqlite database file.
     *
     * @return string The absolute path to the Sqlite database file.
     */
    public function getFilePath()
    {
        return rtrim($this->wpRootDir, '\\/') . '/' . $this->getFileName();
    }

    /**
     * Returns the database file name.
     *
     * @return string The database file name.
     */
    public function getFileName()
    {
        return $this->fileName;
    }

    /**
     * {@inheritDoc}
     */
    public function setWpRootDir($wpRootDir)
    {
        $this->wpRootDir = $wpRootDir;

        return $this;
    }

    /**
     * Creates the database if required.
     *
     * @return WordPressDatabaseInterface The object.
     *
     * @throws \RuntimeException If the database file cannot be created.
     */
    public function create()
    {
        $created = touch($this->getFilePath());

        if (!$created) {
            throw new \RuntimeException("Could not created Sqlite database file [{$this->getFilePath()}].");
        }

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function setInstallation(Installation $installation)
    {
        $this->installation = $installation;

        $dest = $installation->getWpContentDir('db.php');
        copy(wpbrowser_includes_dir('/sqlite-db.php'), $dest);

        if (file_exists($dest)) {
            throw new \RuntimeException("Could not copy db.php drop-in into wp-content folder [$dest].");
        }
    }

    /**
     * {@inheritDoc}
     */
    public function shouldSkipCheck()
    {
        return true;
    }
}
