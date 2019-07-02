<?php
/**
 * Scaffolds and manages temporary WordPress installations.
 *
 * @package tad\WPBrowser\Traits
 */

namespace tad\WPBrowser\Traits;

use tad\WPBrowser\Environment\Database\Sqlite;
use tad\WPBrowser\Environment\Installation;
use tad\WPBrowser\Interfaces\WordPressDatabaseInterface;

/**
 * Trait WithWordPressInstallations
 *
 * @package tad\WPBrowser\Traits
 */
trait WithWordPressInstallations
{
    use WithFaker;

    /**
     * The last WordPress installation object.
     *
     * @var Installation
     */
    protected $lastWpInstallation;

    /**
     * Scaffolds, creating the directory, installing and configuring WordPress in it, a WordPress installation.
     *
     * @param null                            $installationsRootDir The installations root directory, if not provided
     *                                                              it will default to the Codeception output directory.
     * @param string                          $version              The WordPress version to install, valid values are
     *                                                              version numbers (e.g. `3.5`) or `latest` to install
     *                                                              the latest available version.
     * @param WordPressDatabaseInterface|null $database             An WordPress database installation instance.
     *
     * @return Installation This object.
     * @throws \tad\WPBrowser\Exceptions\InstallationException If there's an exception while building the installation.
     * @throws \tad\WPBrowser\Exceptions\WpCliException If there's an exceptions while issuing the installation wp-cli
     *                                                  set up commands.
     * @throws \tad\WPBrowser\Exceptions\SQLiteException
     */
    protected function scaffoldWpInstallation(
        $installationsRootDir = null,
        $version = 'latest',
        WordPressDatabaseInterface $database = null
    ) {
        $rootDir = $this->createWpInstallationRootDirectory($installationsRootDir);
        $database = $database ?: $this->createWpInstallationSqliteDatabase($installationsRootDir);
        $this->lastWpInstallation = new Installation($rootDir, $version);
        $database->setInstallation($this->lastWpInstallation);
        $this->lastWpInstallation->download()->configure($database)->install();

        return $this->lastWpInstallation;
    }

    /**
     * Creates the test installation root directory in the installations (plural) root directory.
     *
     * @param null|string $installationsRootDir The root directory to use to scaffold installations. If not provided
     *                                          then it will default to the Codeception output directory.
     * @param string|null $installationDir      The directory the installation will be created into.
     *
     * @return string The absolute path to the root directory.
     */
    protected function createWpInstallationRootDirectory($installationsRootDir = null, $installationDir = null)
    {
        $this->setUpFaker();
        $installationsRootDir = $installationsRootDir ?
            $installationsRootDir
            : codecept_output_dir('/wp-installations');
        if ($installationDir === null) {
            $installationDir = strtolower($this->faker->safeColorName . '-' . $this->faker->animal());
        }
        $installationsRootDir = rtrim($installationsRootDir, '/');
        $rootDir = $installationsRootDir . '/' . $installationDir;

        if (!mkdir($rootDir, 0777, true) && !is_dir($rootDir)) {
            throw new \RuntimeException(sprintf(
                'Could not create WordPress installation root folder [%s].',
                $rootDir
            ));
        }

        return $rootDir;
    }

    /**
     * Builds and returns a SQLite database object for a WordPress installation.
     *
     * @param string The absolute path to the WordPress installation root directory.
     *
     * @return Sqlite The SQLite database object.
     *
     * @throws \tad\WPBrowser\Exceptions\SQLiteException If there's an issue creating the SQLite database.
     */
    protected function createWpInstallationSqliteDatabase($installationRootDir)
    {
        $file = rtrim($installationRootDir, '\\/') . '/database.sqlite';
        $database = new Sqlite('sqlite:' . $file, '', '');
        $database->createDatabase();

        return $database;
    }
}
