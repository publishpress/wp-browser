<?php
/**
 * Provides methods to use a Sqlite database in tests.
 *
 * @package tad\WPBrowser\Traits
 */

namespace tad\WPBrowser\Traits;

use tad\WPBrowser\Environment\Database\Sqlite;
use tad\WPBrowser\Exceptions\SQLiteException;

/**
 * Trait WithSqliteDatabase
 *
 * @package tad\WPBrowser\Traits
 */
trait WithSqliteDatabase
{
    use WithTestnames;

    /**
     * Creates and returns a Sqlite database object
     *
     * @param string|null $name            The database file name, e.g. `test.sqlite`; if not provided then the name
     *                                     will be generated from the test case calling it.
     * @param string|null $dir             The absolute path to the directory the database file should be created in.
     *                                     If not provided the file will be created in the
     *                                     `tests/_output/databases/sqlite` directory.
     * @param bool $force Whether to force the database file creation or not.
     *
     * @return Sqlite The created Sqlite database.
     *
     * @throws SQLiteException If the parent directory or the database file cannot be created.
     * @throws \ReflectionException If there's an issue reflecting on the current test case to get its name.
     */
    protected function createSqliteDatabase($name = null, $dir = null, $force = true)
    {
        $name = $name ?: $this->getTestName();
        $dir = $dir ?: codecept_output_dir('databases/sqlite');
        $dsn = $name;

        if ($name !== ':memory:') {
            $dbName = basename(preg_replace('/\\.sqlite$/', '', $name) . '.sqlite');
            $dbFile = $dir . '/' . $dbName;
            $dsn = 'sqlite:' . $dbFile;
        }

        $db = new Sqlite($dsn, '', '');

        return $db->createDatabase($force);
    }

    /**
     * Takes a snapshot of the current SQLite database file by copying it to the specified location, or to a default
     * location if none is specified.
     *
     * @param string|null $name The snapshot name or path, relative to the snapshots folder.
     *
     * @throws SQLiteException If the snapshot creation fails.
     * @throws \ReflectionException If there's an issue reflecting on the current test case.
     */
    protected function snapshotSqliteDatabase($name = null)
    {
        $snapshotName = $name ?
            preg_replace('/\\.sqlite$/', '', trim($name, '\\/'))
            : $this->getTestName();
        $dumpFile = $this->getTestDir('__db_snapshots__/' . $snapshotName . '.sqlite');
        Sqlite::fromGlobalWpdb()->dumpTo($dumpFile);
    }

    /**
     * Replaces the current database file with the specified one.
     *
     * This will, in fact, reset the SQLite database to the state of the loaded file.
     *
     * @param string $name The file name, that will be resolved relative to the test case snapshots directory, or the
     *                     absolute path to the SQLite file to load.
     *
     * @throws SQLiteException If the specified file cannot be found or cannot be copied.
     * @throws \ReflectionException If there's an issue reflecting on the current test case.
     */
    protected function loadSqliteSnapshot($name)
    {
        $file = $name;
        if (!file_exists($file)) {
            $snapshotName = preg_replace('/\\.sqlite$/', '', trim($name, '\\/'));
            $file = $this->getTestDir('__db_snapshots__/' . $snapshotName . '.sqlite');
        }

        if (!file_exists($file)) {
            throw SQLiteException::becauseDatabaseFileDoesNotExist($file);
        }

        $dbFile = $this->getSqliteDbFilePath();
        $copied = copy($file, $dbFile);

        if ($copied === false) {
            throw SQLiteException::becauseDatabaseFileReplacementFailed($file, $dbFile);
        }

        $this->reconnectToSqliteDatabase();
    }

    /**
     * Returns the absolute path to the SQLite database file currently being used.
     *
     * @return string The absolute path to the SQLite database file currently being used.
     * @throws SQLiteException If there's the constants defining the database directory and file are not set.
     */
    protected function getSqliteDbFilePath()
    {
        if (!(defined('DB_DIR') && defined('DB_FILE'))) {
            throw SQLiteException::becauseDbConstantsAreNotDefined();
        }

        $dbFile = rtrim(DB_DIR, '\\/') . '/' . trim(DB_FILE, '\\/');

        if (!file_exists($dbFile)) {
            throw SQLiteException::becauseDatabaseFileDoesNotExist($dbFile);
        }

        return $dbFile;
    }

    /**
     * Reconnects to the SQLite database.
     */
    protected function reconnectToSqliteDatabase()
    {
        global $wpdb;
        unset($GLOBALS['@pdo']);
        $wpdb->db_connect();
    }
}
