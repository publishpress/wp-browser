<?php
/**
 * Models a WordPress installation.
 *
 * @package tad\WPBrowser\Environment
 */

namespace tad\WPBrowser\Environment;

use GuzzleHttp\Exception\ConnectException;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;
use tad\WPBrowser\Exceptions\InstallationException;
use tad\WPBrowser\Exceptions\ProcessException;
use tad\WPBrowser\Exceptions\WpCliException;
use tad\WPBrowser\Interfaces\WordPressDatabaseInterface;
use tad\WPBrowser\Traits\WithFaker;
use tad\WPBrowser\Traits\WithHTTPRequests;
use tad\WPBrowser\Traits\WithProcesses;
use tad\WPBrowser\Traits\WithWpCli;

/**
 * Class Installation
 *
 * @package tad\WPBrowser\Environment
 */
class Installation
{
    use WithWpCli;
    use WithFaker;
    use WithProcesses;
    use WithHTTPRequests;

    /**
     * The absolute path to the installation root directory.
     *
     * @var string
     */
    protected $rootDir;

    /**
     * The WordPress version requested in the object constructor. Differently from the `$wpVersion` property this might
     * be an alias, like `latest`.
     *
     * @var string
     */
    protected $requestVersion;

    /**
     * The installed WordPress version; this will always be a number.
     *
     * @var string
     */
    protected $wpVersion;

    /**
     * The installation WordPress locale.
     *
     * @var string
     */
    protected $locale = 'en_US';

    /**
     * Whether to skip WordPress default plugins and themes during download or not.
     *
     * @var bool
     */
    protected $skipContent = false;

    /**
     * The installation URL.
     *
     * @var string
     */
    protected $url;

    /**
     * The site title.
     *
     * @var string
     */
    protected $title;

    /**
     * The site administrator user login.
     *
     * @var string
     */
    protected $adminUser;

    /**
     * The site administrator user password.
     *
     * @var string
     */
    protected $adminPassword;

    /**
     * The site administrator user email.
     *
     * @var string.
     */
    protected $adminEmail;

    /**
     * Whether to skip email notifications during installation or not.
     *
     * @var bool
     */
    protected $skipEmail = true;

    /**
     * Whether to skip salt generation during installation or not.
     *
     * @var bool
     */
    protected $skipSalts = true;

    /**
     * Whether to check for the db connection during the installation or not.
     *
     * @var bool
     */
    protected $skipCheck = true;

    /**
     * The name of the database used by the installation.
     *
     * @var string
     */
    protected $dbName;

    /**
     * The database user used by the installation.
     *
     * @var string
     */
    protected $dbUser;

    /**
     * The database password used by the installation.
     *
     * @var string
     */
    protected $dbPassword;

    /**
     * The database host used by the installation.
     *
     * @var string
     */
    protected $dbHost = 'localhost';

    /**
     * The database prefix used by the installation.
     *
     * @var string
     */
    protected $dbPrefix = 'wp_';
    /**
     * The database charachter set used by the installation.
     *
     * @var string
     */
    protected $dbCharset = 'utf8';

    /**
     * The database collation used by the installation.
     *
     * @var string
     */
    protected $dbCollate = '';

    /**
     * Extra PHP lines added in the installation wp-config.php file.
     *
     * @var string
     */
    protected $extraPhp = '';

    /**
     * The installation database object.
     *
     * @var WordPressDatabaseInterface
     */
    protected $wordPressDatabase;

    /**
     * Whether the current installation is installed or not.
     *
     * @var bool
     */
    protected $isInstalled;

    /**
     * Whether the current installation is configured or not.
     *
     * @var bool
     */
    protected $isConfigured;

    /**
     * The localhost port the installation is being served on or `false` if the installation is not being served.
     *
     * @var int|bool
     */
    protected $serverPort = false;

    /**
     * The localhost URL the installation is being served on or `false` if the installation is not being served.
     *
     * @var string|bool
     */
    protected $serverUrl = false;

    /**
     * The default server host.
     *
     * @var string
     */
    protected $defaultServerHost = 'localhost';

    /**
     * Whether the current installation is being served or not.
     *
     * @var bool
     */
    protected $isBeingServed = false;

    /**
     * The process instance handling the wp-cli `server` command.
     *
     * @var Process
     */
    protected $serverProcess;

    /**
     * The localhost IP address or domain.
     *
     * @var string
     */
    protected $localhostAddress = 'localhost';

    /**
     * Installation constructor.
     *
     * @param string $rootDir              The absolute path to the installation root directory.
     * @param string $version              The WordPress version to install, valid values are version numbers
     *                                     (e.g. `3.5`) or `latest` to install the latest available version.
     * @param string $locale               The locale string to use.
     * @param bool   $skipContent          Whether to skip WordPress default plugins and themes in the download or not.
     */
    public function __construct($rootDir, $version = 'latest', $locale = 'en_US', $skipContent = false)
    {
        $this->rootDir = rtrim($rootDir, '/\\');
        $this->requestVersion = $version;
        $this->locale = $locale;
        $this->skipContent = (bool)$skipContent;
    }

    /**
     * Downloads the WordPress version specified by the installation settings in the installation root directory.
     *
     * @return $this This object.
     *
     * @throws InstallationException If the download process fails at any step.
     * @throws WpCliException If the `core download` command building fails.
     */
    public function download()
    {
        $this->setUpWpCli($this->getRootDir());

        $options = [
            'locale' => $this->locale,
            'version' => $this->requestVersion,
        ];

        if ($this->skipContent) {
            $options['skip-content'] = '1';
        }

        $command = array_merge(['core', 'download'], $this->wpCliOptions($options));

        $this->createRootDir();

        codecept_debug('Downloading WordPress with command: ' . json_encode($command));

        $download = $this->executeWpCliCommand($command);

        codecept_debug($download->getOutput());

        if ($download->getExitCode() !== 0) {
            throw InstallationException::becauseDownloadFailed($this, $download);
        }

        $this->wpVersion = $this->getVersion(true);

        return $this;
    }

    /**
     * Returns the absolute path to the installation root directory.
     *
     * @return string The absolute path to the installation root directory.
     */
    public function getRootDir($path = null)
    {
        return empty($path) ?
            $this->rootDir
            : $this->rootDir . '/' . trim($path, '/\\');
    }

    /**
     * Creates the installation root directory.
     *
     * @throws InstallationException If the installation root directory cannot be created.
     */
    protected function createRootDir()
    {
        if (!is_dir($this->rootDir)
            && !mkdir($destDir = $this->rootDir, 0777, true) && !is_dir($destDir)
        ) {
            throw InstallationException::becauseRootDirCannotBeCreated($this);
        }
    }

    /**
     * Returns the installed WordPress version.
     *
     * @param bool $refetch Whether to use the cached value or to rerun the `core version` command and refetch the
     *                      version.
     *
     * @return string The installed WordPress version, e.g. `5.2.1`.
     */
    public function getVersion($refetch = false)
    {
        if (null === $this->wpVersion || $refetch) {
            $this->wpVersion = trim($this->executeWpCliCommand(['core', 'version'])->getOutput(), PHP_EOL);
        }

        return $this->wpVersion;
    }

    /**
     * Installs the installation.
     *
     * @param string|null $url           The installation URL to use; defaults to a random one.
     * @param string|null $title         The installation site title; defaults to a random one.
     * @param string|null $adminUser     The installation administrator user name; defaults to a random one.
     * @param string|null $adminPassword The installation administrator password; defaults to a random one.
     * @param null        $adminEmail    The installation administrator email; defaults to a random one.
     *
     * @return $this This.
     *
     * @throws InstallationException If the installation installation fails.
     */
    public function install($url = null, $title = null, $adminUser = null, $adminPassword = null, $adminEmail = null)
    {
        $this->setUpFaker($this->locale);
        $this->setUpWpCli($this->getRootDir());

        $this->url = $url ?: $this->faker->url;
        $this->title = $title ?: $this->title;
        $this->adminUser = $adminUser ?: $this->faker->userName;
        $this->adminPassword = $adminPassword ?: $this->faker->password;
        $this->adminEmail = $adminEmail ?: $this->faker->email;

        $options = [
            'path' => $this->rootDir,
            'url' => $this->url,
            'title' => $this->title,
            'admin_user' => $this->adminUser,
            'admin_password' => $this->adminPassword,
            'admin_email' => $this->adminEmail,
            'skip-email' => '1',
        ];

        $command = array_merge(['core', 'install'], $this->wpCliOptions($options));

        codecept_debug('Installing WordPress with command: ' . json_encode($command));

        $install = $this->executeWpCliCommand($command);

        codecept_debug($install->getOutput());

        if ($install->getExitCode() !== 0) {
            throw InstallationException::becauseInstallationFailed($this, $install);
        }

        return $this;
    }

    /**
     * Returns the absolute path to the installation wp-content directory.
     *
     * @param string|null $path A path to append to the wp-content directory path.
     *
     * @return string The absolute path to the installation wp-content directory.
     */
    public function getWpContentDir($path = null)
    {
        return $path === null ?
            $this->getRootDir('wp-content')
            : $this->getRootDir('wp-content/' . trim($path, '\\/'));
    }

    /**
     * Configures the WordPress installation.
     *
     * @param WordPressDatabaseInterface $database The database instance to get configuration information from.
     *
     * @return $this This.
     * @throws InstallationException If the installation configuration fails.
     * @throws WpCliException If wp-cli configuration fails.
     */
    public function configure(WordPressDatabaseInterface $database)
    {
        $this->setUpWpCli($this->getRootDir());

        $this->wordPressDatabase = $database;

        $options = [
            'dbname' => $this->wordPressDatabase->getName(),
            'dbuser' => $this->wordPressDatabase->getUser(),
            'dbpass' => $this->wordPressDatabase->getPassword(),
            'dbhost' => $this->wordPressDatabase->getHost(),
            'dbprefix' => $this->wordPressDatabase->getTablePrefix(),
            'dbcharset' => $this->wordPressDatabase->getCharset(),
            'dbcollate' => $this->wordPressDatabase->getCollation(),
            'locale' => $this->locale,
            'extra-php' => $this->wordPressDatabase->getExtraPhp() . PHP_EOL . $this->extraPhp,
        ];

        if ($this->wordPressDatabase->shouldSkipCheck()) {
            $options['skip-check'] = 1;
        }

        $command = array_merge(['config', 'create'], $this->wpCliOptions($options));

        codecept_debug('Configuring WordPress with command: ' . json_encode($command));

        $configure = $this->executeWpCliCommand($command);

        codecept_debug($configure->getOutput());

        if ($configure->getExitCode() !== 0) {
            throw InstallationException::becauseConfigurationFailed($this, $configure);
        }

        return $this;
    }

    /**
     * Returns whether the installation is installed or not.
     *
     * @param bool $refetch Whether to use the cached value, if available, or to force a new check.
     *
     * @return bool Whether the installation is installed or not.
     */
    public function isInstalled($refetch = false)
    {
        if (null === $this->isInstalled || $refetch) {
            $isInstalled = $this->setUpWpCli($this->rootDir)->executeWpCliCommand(['core', 'is-installed']);
            $this->isInstalled = $isInstalled->getExitCode() === 0;
        }

        return $this->isInstalled;
    }

    /**
     * Returns whether the installation has a wp-config.php file or not.
     *
     * @return bool Whether the installation has a wp-config.php file or not.
     */
    public function isConfigured()
    {
        return file_exists($this->getRootDir('wp-config.php'));
    }

    /**
     * Returns the localhost URL the installation is being served on.
     *
     * @return string|bool The localhost URL the installation is being served on, or `false` if the installation is not
     *                  being served.
     */
    public function getServerUrl()
    {
        return $this->serverUrl ?: false;
    }

    /**
     * Returns whether the installation is currently being served or not.
     *
     * return bool Whether the installation is currently being served or not.
     */
    public function isBeingServed()
    {
        return $this->isBeingServed;
    }

    /**
     * Returns the localhost port the installation is being served on.
     *
     * @return bool|int The localhost port the installation is being served on, or `false` if the installation is not
     *                  being served.
     */
    public function getServerPort()
    {
        return $this->serverPort ?: false;
    }

    /**
     * Serves the installation on a specific localhost port and returns the installation URL.
     *
     * @param int $port The localhost port to serve the installation on.
     *
     * @return string The URL where the installation is being served.
     * @throws InstallationException If the server command fails.
     * @throws WpCliException If there's an issue while setting up the wp-cli executable.
     */
    public function serve($port = 8080)
    {
        if ($this->isBeingServed()) {
            return $this->serverUrl;
        }

        $this->setUpWpCli($this->getRootDir());

        $serverUrl = "http://{$this->localhostAddress}:{$port}";
        $this->serverUrl = $serverUrl;
        $this->serverPort = $port;

        if (!$this->serverUrl !== $this->url) {
            $this->url = $this->serverUrl;
            $this->updateOptionWithWpcli('siteurl', $this->serverUrl);
            $this->updateOptionWithWpcli('home', $this->serverUrl);
            codecept_debug(sprintf('Installation URL changed to [%s]', $this->serverUrl));
        }

        $serverCommand = [
            (new PhpExecutableFinder)->find(),
            '-S',
            "{$this->defaultServerHost}:{$port}",
            '-t',
            $this->getRootDir(),
            $this->getWpCliRouterFilePath(),
        ];

        codecept_debug('Serving WordPress with command: ' . json_encode($serverCommand));

        $this->setHttpRequestsRootUrl($serverUrl);
        $isServerRunning = function () {
            static $tries;
            $tries = (int)$tries++;

            if ($tries > 10) {
                throw InstallationException::becauseInstallationCannotBeServed($this, 'Server failed to start.');
            }

            try {
                $response = $this->requestHead('/')->wait();
            } catch (ConnectException $e) {
                return false;
            }

            return $response->getStatusCode() === 200;
        };

        try {
            $serve = $this->executeBackgroundProcess($serverCommand, $this->getRootDir(), $isServerRunning);
        } catch (ProcessException $e) {
            throw InstallationException::becauseInstallationCannotBeServed($this, $e->getMessage());
        }

        codecept_debug('Server process PID: ' . $serve->getPid());

        $this->isBeingServed = true;
        $this->serverProcess = $serve;

        return $this->serverUrl;
    }

    /**
     * Ensure the installation server process will be killed on destruction of the installation object.
     *
     * @throws WpCliException If the wp-cli server process is running and it cannot be correctly stopped.
     */
    public function __destruct()
    {
        $this->stopServing();
    }

    /**
     * Stops the wp-cli process currently serving the site on localhost.
     *
     * The method will just return if the site is not currently being served.
     *
     * @throws WpCliException If the wp-cli server process cannot be stopped.
     */
    public function stopServing()
    {
        if (!($this->serverProcess instanceof Process && $this->serverProcess->isRunning())) {
            $this->isBeingServed = false;
            $this->serverUrl = false;
            $this->serverPort = false;
            return;
        }

        $pid = $this->serverProcess->getPid();
        codecept_debug("Stopping installation wp-cli server process (PID: {$pid})...");

        $this->serverProcess->stop();

        if ($this->serverProcess->getStatus() !== Process::STATUS_TERMINATED) {
            throw  WpCliException::becauseACommandFailed($this->serverProcess);
        }

        codecept_debug("Installation wp-cli server process (PID: {$pid}) stopped.");

        $this->isBeingServed = false;
        $this->serverUrl = false;
        $this->serverPort = false;
    }

    /**
     * Returns the installation database object, if any.
     *
     * @return WordPressDatabaseInterface
     */
    public function getDatabase()
    {
        return $this->wordPressDatabase;
    }

    /**
     * Executes a wp-cli command in the installation and returns the resulting process.
     *
     * @param array $command The command to execute.
     * @param int   $timeout The command execution timeout.
     *
     * @return Process The process running the wp-cli command.
     * @throws WpCliException If there's an issue building, or running, the command.
     */
    public function cli(array $command = ['version'], $timeout = 60)
    {
        codecept_debug(
            "Executing wp-cli command on installation [{$this->getRootDir()}]: "
            . json_encode($command)
        );
        $this->setUpWpCli($this->getRootDir());

        return $this->executeWpCliCommand($command, $timeout);
    }
}
