<?php
/**
 * The API provided by any object modeling a WordPress database.
 *
 * @package tad\WPBrowser\Environment\Database
 */

namespace tad\WPBrowser\Interfaces;

use tad\WPBrowser\Environment\Installation;

/**
 * Interface WordPressDatabaseInterface
 *
 * @package tad\WPBrowser\Environment\Database
 */
interface WordPressDatabaseInterface
{
    /**
     * Returns the database name.
     *
     * @return string
     */
    public function getName();

    /**
     * Returns the database user.
     *
     * @return string
     *
     */
    public function getUser();

    /**
     * Returns the database password.
     *
     * @return string
     */
    public function getPassword();

    /**
     * Returns the database host.
     *
     * @return string
     */
    public function getHost();

    /**
     * Returns the database table prefix.
     *
     * @return string
     */
    public function getTablePrefix();

    /**
     * Returns the database character set.
     *
     * @return string
     */
    public function getCharset();

    /**
     * Returns the database collation.
     *
     * @return string
     */
    public function getCollation();

    /**
     * Returns the database required extra PHP configuration lines, if any.
     *
     * @return  string
     */
    public function getExtraPhp();

    /**
     * Creates the database if required.
     *
     * @return WordPressDatabaseInterface This.
     */
    public function createDatabase();

    /**
     * Whether the database check should be skipped or not.
     *
     * @return bool
     */
    public function shouldSkipCheck();

    /**
     * Sets the installation the database is associated with.
     *
     * @param Installation $installation The installation using the database.
     *
     * @return WordPressDatabaseInterface This.
     */
    public function setInstallation(Installation $installation);

    /**
     * Resets the installation the database is associated with.
     */
    public function resetInstallation();

    /**
     * Backs-up the database.
     *
     * @param string $destination The path to the file the database should be dumped into.
     *
     * @return bool Whether the dump was successful or not.
     *@see WordPressDatabaseInterface::restore()
     *
     */
    public function dumpTo($destination);
}
