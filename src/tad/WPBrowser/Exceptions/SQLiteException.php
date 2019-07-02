<?php
/**
 * An exception thrown while working with a SQLite database.
 *
 * @package tad\WPBrowser\Exceptions
 */

namespace tad\WPBrowser\Exceptions;

/**
 * Class SQLiteException
 *
 * @package tad\WPBrowser\Exceptions
 */
class SQLiteException extends \Exception
{

    /**
     * Builds and returns an exception to indicate the creation of the database drop-in directory failed.
     *
     * @param string $dir The absolute path to the directory the code tried to create.
     *
     * @return SQLiteException The built exception.
     */
    public static function becauseDropinDirectoryCreationFailed($dir)
    {
        return new static(sprintf('Could not create the database drop-in parent directory [%s]', $dir));
    }

    /**
     * Builds and returns an exception to indicate the creation of the database directory failed.
     *
     * @param string $dir The absolute path to the directory the code tried to create.
     *
     * @return SQLiteException The built exception.
     */
    public static function becauseDatabaseDirectoryCreationFailed($dir)
    {
        return new static(sprintf('Could not create the database file parent directory [%s]', $dir));
    }

    /**
     * Builds and returns an exception to indicate memory databases are not supported.
     *
     * @return SQLiteException The built exception.
     */
    public static function becauseMemoryDatabaseIsNotSupported()
    {
        return new static(':memory: database is not supported');
    }

    /**
     * Builds and returns an exception to indicate the SQLite database drop-in could not be copied to wp-content dir.
     *
     * @param string $dbDropIn The absolute path to the destination database directory.
     *
     * @return SQLiteException The built exception.
     */
    public static function becauseDbDropinCopyFailed($dbDropIn)
    {
        return new static("Could not copy Sqlite db.php drop-in into wp-content folder [{$dbDropIn}].");
    }

    /**
     * Builds and returns an exception to indicate the SQLite database file creation failed.
     *
     * @param string $filename The absolute path to the database file.
     *
     * @return SQLiteException The built exception.
     */
    public static function becauseDatabaseFileCreationFailed($filename)
    {
        return new static("Could not created Sqlite database file [{$filename}].");
    }

    /**
     * Builds and returns an exception to indicate the SQLite database file deletion failed.
     *
     * @param string $filename The absolute path to the database file.
     *
     * @return SQLiteException The built exception.
     */
    public static function becauseDatabaseFileDeletionFailed($filename)
    {
        return new static("Could not delete Sqlite database file [{$filename}].");
    }

    /**
     * Builds and returns an exception to indicate the SQLite database drop-in file deletion failed.
     *
     * @param string $filename The absolute path to the database drop-in file.
     *
     * @return SQLiteException The built exception.
     */
    public static function becauseDbDropinDeletionFailed($filename)
    {
        return new static("Could not delete Sqlite drop-in file [{$filename}].");
    }

    /**
     * Builds and returns an exception to indicate the SQLite database snapshot failed.
     *
     * @param string $dbFile The absolute path to the original database file.
     * @param string $file   The absolute path to the destination snapshot file.
     *
     * @return SQLiteException The built exception.
     */
    public static function becauseDatabaseSnapshotFailed($dbFile, $file)
    {
        return new static("Failed copy of database [{$dbFile}] into snapshot [$file].");
    }

    /**
     * Builds and returns an exception to indicate the SQLite database constants are not set.
     *
     * @return SQLiteException The built exception.
     */
    public static function becauseDbConstantsAreNotDefined()
    {
        return new static('The `DB_DIR` and/or `DB_FILE` constants are not set; ' .
            'are your running this method too early?');
    }

    /**
     * Builds and returns an exception to indicate the SQLite database file does not exist.
     *
     * @param string $dbFile The absolute path to the database file.
     *
     * @return SQLiteException The built exception.
     */
    public static function becauseDatabaseFileDoesNotExist($dbFile)
    {
        return new static("The database file [{$dbFile}] does not exist.");
    }

    /**
     * Builds and returns an exception to indicate the SQLite database could not be replaced.
     *
     * @param string $file   The absolute path to the destination snapshot file.
     * @param string $dbFile The absolute path to the original database file.
     *
     * @return SQLiteException The built exception.
     */
    public static function becauseDatabaseFileReplacementFailed($file, $dbFile)
    {
        return new static("Failed copy of snapshot file [{$file}] into database file [$dbFile].");
    }

    /**
     * Builds and returns an exception to indicate the SQLite database could not be backed up.
     *
     * @param string $current The absolute path to the database current file.
     * @param string $backup  The absolute path to the backup database file.
     *
     * @return SQLiteException The built exception.
     */
    public static function becauseDatabaseFileBackupFailed($current, $backup)
    {
        return new static("Failed backup of current database file [{$current}] into backup file [$backup].");
    }

    /**
     * Builds and returns an exception to indicate the SQLite database could not be restored from a file backup.
     *
     * @param string $backup  The absolute path to the backup database file.
     * @param string $current The absolute path to the database current file.
     *
     * @return SQLiteException The built exception.
     */
    public static function becauseDatabaseFileRestoreFailed($backup, $current)
    {
        return new static("Failed restore of database backup file [{$backup}] into current database file [$current].");
    }

    /**
     * Builds and returns an exception to indicate the SQLite database ATTACH file instruction failed.
     *
     * @param string $file The database file that failed to ATTACH.
     *
     * @return SQLiteException The built exception.
     */
    public static function becauseFileAttachmentFailed($file)
    {
        return new static("Failed attaching database file [{$file}] to current database.");
    }

    /**
     * Builds and returns an exception to indicate the SQLite database DETACH file instruction failed.
     *
     * @param string $file The database file that failed to DETACH.
     *
     * @return SQLiteException The built exception.
     */
    public static function becauseFileDetachmentFailed($file)
    {
        return new static("Failed detaching database file [{$file}] from current database.");
    }

    /**
     * Builds and returns an exception to indicate a query failed.
     *
     * @param string $query The SQL query that was being executed.
     * @param string $error The error  associated with the failure.
     *
     * @return SQLiteException The built exception.
     */
    public static function becauseQueryFailed($query, $error)
    {
        return new static('Query execution failed:' . json_encode(['query' => $query, 'error' => $error]));
    }
}
