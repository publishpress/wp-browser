<?php
/**
 * Scaffolds and manages temporary WordPress installations.
 *
 * @package tad\WPBrowser\Traits
 */

namespace tad\WPBrowser\Traits;

use tad\WPBrowser\Environment\Database\WordPressDatabaseInterface;
use tad\WPBrowser\Environment\Installation;

/**
 * Trait WithWordPressInstallations
 *
 * @package tad\WPBrowser\Traits
 */
trait WithWordPressInstallations
{
    use WithFaker;

    /**
     * The last installation object.
     *
     * @var Installation
     */
    protected $lastInstallation;

    /**
     * Scaffolds, creating the directory, installing and configuring WordPress in it, a WordPress installation.
     *
     * @param null                            $installationsRootDir The installations root directory, if not provided
     *                                                              it will default to the system temp directory.
     * @param string                          $version              The WordPress version to install, valid values are
     *                                                              version numbers
     *                                                              (e.g. `3.5`) or `latest` to install the latest
     *                                                              available version.
     * @param WordPressDatabaseInterface|null $database             An WordPress database installation instance.
     *
     * @return Installation This object.
     */
    protected function scaffoldWpInstallation(
        $installationsRootDir = null,
        $version = 'latest',
        WordPressDatabaseInterface $database = null
    ) {
        $rootDir = $this->createRootDir($installationsRootDir);
        $database->setWpRootDir($rootDir)->create();
        $this->lastInstallation = new Installation($rootDir, $version);
        $this->lastInstallation->download()->configure($database)->install();
        $database->setInstallation($this->lastInstallation);

        return $this->lastInstallation;
    }

    /**
     * Creates the test installation root directory in the installations (plural) root directory.
     *
     * @param null|string $installationsRootDir The root directory to use to scaffold installations. If not provided
     *                                          then it will default to the system temp directory.
     *
     * @return string The absolute path to the root directory.
     */
    private function createRootDir($installationsRootDir = null)
    {
        $this->setUpFaker();
        $installationsRootDir = $installationsRootDir ?
            $installationsRootDir
            : sys_get_temp_dir() . '/wp-installations';
        $installationsRootDir = rtrim($installationsRootDir, '/');
        $rootDir = $installationsRootDir . '/' . $this->faker->safeColorName . '-'
            . preg_replace('/[\W]/', '-', $this->faker->userName);

        if (!mkdir($rootDir, 0777, true) && !is_dir($rootDir)) {
            throw new \RuntimeException(sprintf(
                'Could not create WordPress installation root folder [%s].',
                $rootDir
            ));
        }

        return $rootDir;
    }
}
