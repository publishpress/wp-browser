<?php
/**
 * Models a WordPress installation.
 *
 * @package tad\WPBrowser\Environment
 */

namespace tad\WPBrowser\Environment;

use Codeception\Util\Template;
use tad\WPBrowser\Environment\Database\WordPressDatabaseInterface;
use tad\WPBrowser\Traits\WithFaker;
use tad\WPBrowser\Traits\WithWpCli;
use tad\WPBrowser\WPCLI\BufferLogger;
use WP_CLI\Loggers\Base;

/**
 * Class Installation
 *
 * @package tad\WPBrowser\Environment
 */
class Installation
{
    use WithWpCli;
    use WithFaker;

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
     * The Core command instance the installation should use to download and install WordPress.
     *
     * @var \Core_Command
     */
    protected $coreCommand;

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
     * The logger used to log wp-cli messages.
     *
     * @var Base
     */
    protected $wpCliLogger;

    /**
     * An instance of the wp-cli config command
     *
     * @var \Config_Command
     */
    protected $configTemplate;

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
    protected $db;

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
        $this->requireWpCliFiles();

        if (!(is_dir($rootDir) && is_writable($rootDir))) {
            throw new \InvalidArgumentException("Installation root directory [{$rootDir}] is not valid.");
        }

        $this->rootDir = rtrim($rootDir, '/\\');
        $this->requestVersion = $version;
        $this->locale = $locale;
        $this->skipContent = $skipContent;

        $this->wpCliLogger = new BufferLogger();
    }

    public function download()
    {
        \WP_CLI::set_logger($this->wpCliLogger);

        $coreCommand = $this->coreCommand ?: new \Core_Command();
        $coreCommand->download([], [
            'path' => $this->rootDir,
            'locale' => $this->locale,
            'version' => $this->requestVersion,
            'skipContent' => $this->skipContent,
            'force' => false,
        ]);

        return $this;
    }

    public function install($url = null, $title = null, $adminUser = null, $adminPassword = null, $adminEmail = null)
    {
        $this->setUpFaker($this->locale);
        $this->setUpWpCli($this->getRootDir());

        $this->url = $url ?: $this->faker->url;
        $this->title = $title ?: $this->title;
        $this->adminUser = $adminUser ?: $this->faker->userName;
        $this->adminPassword = $adminPassword ?: $this->faker->password;
        $this->adminEmail = $adminEmail ?: $this->faker->email;

        $this->executeWpCliCommand([
            'install',
            $this->wpCliOptions([
                'path' => $this->rootDir,
                'url' => $this->url,
                'title' => $this->title,
                'admin_user' => $this->adminUser,
                'admin_password' => $this->adminPassword,
                'admin_email' => $this->adminEmail,
                'skip-email' => true,
            ])
        ]);

        return $this;
    }

    /**
     * Sets the core command the installation should use to download and install WordPress.
     *
     * @param \Core_Command $coreCommand A Core command instance, from the wp-cli package.
     */
    public function setCoreCommand(\Core_Command $coreCommand)
    {
        $this->coreCommand = $coreCommand;
    }

    /**
     * Returns the wp-cli logger instance used during the installation operations.
     *
     * @return Base The wp-cli logger instance used during installation operations.
     */
    public function getWpCliLogger()
    {
        return $this->wpCliLogger;
    }

    /**
     * Sets the wp-cli logger that should be used to log messages.
     *
     * @param Base $wpCliLogger The logger instance to use.
     */
    public function setWpCliLogger($wpCliLogger)
    {
        $this->wpCliLogger = $wpCliLogger;
    }

    /**
     * Sets the configuration command instance the installation should use to configure itself.
     *
     * @param \Config_Command $configTemplate The configuration command instance the installation should use to
     *                                        configure itself.
     */
    public function setConfigTemplate($configTemplate)
    {
        $this->configTemplate = $configTemplate;
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
     * Configures the WordPress installation.
     *
     * @param WordPressDatabaseInterface|null $database The database instance to get configuration information from.
     *
     * @return $this This object.
     */
    public function configure(WordPressDatabaseInterface $database)
    {
        $this->db = $database;

        \WP_CLI::set_logger($this->wpCliLogger);
        $configTemplate = $this->configTemplate ?:
            new  Template(file_get_contents(__DIR__ . '/wp-config.php.template'));

        $arr = [
            'dbName' => $this->db->getName(),
            'dbUser' => $this->db->getUser(),
            'dbPass' => $this->db->getPassword(),
            'dbHost' => $this->db->getHost(),
            'dbPrefix' => $this->db->getTablePrefix(),
            'dbCharset' => $this->db->getCharset(),
            'dbCollate' => $this->db->getCollation(),
            'locale' => $this->locale,
            'extraPhp' => $this->db->getExtraPhp() . PHP_EOL . $this->extraPhp,
        ];

        foreach ($arr as $key => $value) {
            $configTemplate->place($key, $value);
        }

        $wpConfigFile = $this->getRootDir('wp-config.php');
        $put = file_put_contents($wpConfigFile, $configTemplate->produce(), LOCK_EX);

        if (!$put) {
            throw new \RuntimeException("Could not write installation wp-config.php file [{$wpConfigFile}]");
        }

        return $this;
    }
}
