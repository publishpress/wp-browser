<?php
/**
 * Installation specific exception.
 *
 * @package tad\WPBrowser\Environment
 */

namespace tad\WPBrowser\Exceptions;

use Symfony\Component\Process\Process;
use tad\WPBrowser\Environment\Installation;

/**
 * Class InstallationException
 *
 * @package tad\WPBrowser\Environment
 */
class InstallationException extends \Exception
{

    /**
     * Builds and returns an exception to indicate an installation configuration failed.
     *
     * @param Installation $installation         The installation object.
     * @param Process      $configurationProcess The failed configuration process.
     *
     * @return InstallationException The built exception.
     */
    public static function becauseConfigurationFailed(
        Installation $installation,
        Process $configurationProcess
    ) {
        return new static(sprintf(
            'Cannot configure installation in [%s]; %s)',
            $installation->getRootDir(),
            $configurationProcess->getErrorOutput()
        ));
    }

    /**
     * Builds and returns an exception to indicate an installation download failed.
     *
     * @param Installation $installation    The installation object.
     * @param Process      $downloadProcess The failed download process.
     *
     * @return InstallationException The built exception.
     */
    public static function becauseDownloadFailed(Installation $installation, Process $downloadProcess)
    {
        return new static(sprintf(
            'Cannot download WordPress in [%s]; %s)',
            $installation->getRootDir(),
            $downloadProcess->getErrorOutput()
        ));
    }

    /**
     * Builds and returns an exception to indicate an installation installation failed.
     *
     * @param Installation $installation        The installation object.
     * @param Process      $installationProcess The failed installation process.
     *
     * @return InstallationException The built exception.
     */
    public static function becauseInstallationFailed(Installation $installation, Process $installationProcess)
    {
        return new static(sprintf(
            'Cannot install WordPress in [%s]; %s)',
            $installation->getRootDir(),
            $installationProcess->getErrorOutput()
        ));
    }

    /**
     * Builds and returns an exception to indicate an installation root dir cannot be created.
     *
     * @param Installation $installation The installation object.
     *
     * @return InstallationException The built exception.
     */
    public static function becauseRootDirCannotBeCreated(Installation $installation)
    {
        return new static(sprintf(
            'Cannot create the installation root directory [%s])',
            $installation->getRootDir()
        ));
    }

    /**
     * Builds and returns an exception to indicate an installation cannot be served.
     *
     * @param Installation $installation The installation object.
     * @param Process      $serveProcess The wp-cli server process.
     *
     * @return InstallationException The built exception.
     */
    public static function becauseInstallationCannotBeServed(Installation $installation, Process $serveProcess)
    {
        return new static(sprintf(
            'Cannot serve the installation from docroot [%s] at URL [%s])',
            $installation->getRootDir(),
            $installation->getServerUrl()
        ));
    }
}
