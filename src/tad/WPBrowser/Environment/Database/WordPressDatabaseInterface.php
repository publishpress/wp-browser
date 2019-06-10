<?php
/**
 * The API provided by any object modeling a WordPress database.
 *
 * @package tad\WPBrowser\Environment\Database
 */

namespace tad\WPBrowser\Environment\Database;

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
     * Sets the installation root directory.
     *
     * Some database implementations might need it.
     *
     * @param string $wpRootDir The absolute path to the WordPress installation root directory.
     *
     * @return WordPressDatabaseInterface The object.
     */
    public function setWpRootDir($wpRootDir);


    /**
     * Sets the WordPress installation this database is for.
     *
     * @param Installation $installation
     *
     * @return WordPressDatabaseInterface The object.
     */
    public function setInstallation(Installation $installation);

    /**
     * Creates the database if required.
     *
     * @return WordPressDatabaseInterface The object.
     */
    public function create();

    /**
     * Whether the database check should be skipped or not.
     *
     * @return bool
     */
    public function shouldSkipCheck();
}
