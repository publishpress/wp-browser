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
     * @param string      $reason The reason for the server process failure.
     *
     * @return InstallationException The built exception.
     */
    public static function becauseInstallationCannotBeServed(Installation $installation, $reason)
    {
        return new static(sprintf(
            'Cannot serve the installation from docroot [%s] at URL [%s]: %s',
            $installation->getRootDir(),
            $installation->getServerUrl(),
            $reason
        ));
    }

    /**
     * Builds and returns an exception to indicate an installation cannot be served on a port as alraedy occupied.
     *
     * @param Installation $installation The installation object.
     * @param int      $port The unavailable port.
     *
     * @return InstallationException The built exception.
     */
    public static function becauseInstallationCannotBeServedOnOccupiedPort(Installation $installation, $port)
    {
        return new static(sprintf(
            'Cannot serve the installation from docroot [%s] on port [%d] as it is already used.',
            $installation->getRootDir(),
            $port
        ));
    }
}
