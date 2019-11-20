<?php /** @noinspection PhpComposerExtensionStubsInspection */

namespace Codeception\Template;

use Codeception\Exception\ModuleConfigException;
use Dotenv\Dotenv;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Yaml\Yaml;
use tad\WPBrowser\Exceptions\WpCliException;
use tad\WPBrowser\Installation\Installation;
use tad\WPBrowser\Installation\InstallationException;
use tad\WPBrowser\Installation\NotFoundInstallation;
use tad\WPBrowser\Monads\Maybe;
use tad\WPBrowser\Project\Project;
use tad\WPBrowser\Project\ProjectException;
use tad\WPBrowser\Services\Db\PDOQueryRunner;
use tad\WPBrowser\Template\Data;
use tad\WPBrowser\Traits\WithCustomCliColors;
use tad\WPBrowser\Traits\WithWpCli;
use function tad\WPBrowser\buildDbCredsFromWpCreds;
use function tad\WPBrowser\dbDropInForEnv;
use function tad\WPBrowser\docs;
use function tad\WPBrowser\dumpToFile;
use function tad\WPBrowser\findPluginFile;
use function tad\WPBrowser\findPluginFiles;
use function tad\WPBrowser\findThemesInDir;
use function tad\WPBrowser\findWordPressRootDir;
use function tad\WPBrowser\findWpConfigFile;
use function tad\WPBrowser\findWpDbCreds;
use function tad\WPBrowser\getWpConfigConstant;
use function tad\WPBrowser\getWpContentDir;
use function tad\WPBrowser\importDump;
use function tad\WPBrowser\pathNormalize;
use function tad\WPBrowser\parseUrl;
use function tad\WPBrowser\pathJoin;
use function tad\WPBrowser\pathTail;
use function tad\WPBrowser\putFileReplacement;
use function tad\WPBrowser\renameFile;
use function tad\WPBrowser\slug;
use function tad\WPBrowser\tryDbConnection;
use function tad\WPBrowser\tryTimes;
use function WP_CLI\Utils\launch_editor_for_input;

class Wpbrowser extends Bootstrap
{
    use WithCustomCliColors;
    use WithWpCli;

    /**
     * Whether to output during the bootstrap process or not.
     *
     * @var bool
     */
    protected $quiet = false;

    /**
     * Whether to bootstrap with user interaction or not.
     *
     * @var bool
     */
    protected $noInteraction = false;

    /**
     * The name of the environment file to use.
     *
     * @var string
     */
    protected $envFileName = '';

    /**
     * The project name in slug format, inferred from the working directory.
     *
     * @var string
     */
    protected $projectName = 'project';

    /**
     * The PDO query runner instance.
     *
     * @var PDOQueryRunner
     */
    protected $queryRunner;

    /**
     * The db credentials that will work from the machine setting up the tests.
     *
     * @var array
     */
    protected $workingDbCreds;

    /**
     * The db credentials that will work from the machine setting up the tests, for the test database.
     *
     * @var array
     */
    protected $testDbCreds;
    /**
     * The project currently being tested.
     *
     * @var Project
     */
    protected $project;

    /**
     * The installation containing, or contained, by this project.
     *
     * @var Installation
     */
    protected $installation;

    public function setup()
    {
        // @todo update configuration to new flow.

        $this->customizeOutputColors($this->output, 'cold');
        /** @noinspection PhpUnhandledExceptionInspection */
        $this->checkInstalled($this->workDir);
        $interactive = $this->isInteractive();
        $this->parseNamespace();
        $this->parseActor();

        try {
            $this->project = new Project($this->workDir);
        } catch (ProjectException $e) {
            $this->sayError('Error while setting up the project: ' . $e->getMessage());
            exit(1);
        }

        try {
            $this->installation = new Installation($this->findWpRootDir());
        } catch (InstallationException $e) {
            $this->sayError('Error while collecting the WordPress installation data: ' . $e->getMessage());
            exit(1);
        }

        if ($interactive) {
            $this->sayHi();

            if (!$this->askForAcknowledgment()) {
                $this->sayBye();
                return;
            }
        }

        // @todo inline explanations
//        $this->sayInfo('You can find a long explanation of each question here:');
//        $this->sayInfo(
//            '<bold>' .
//            docs('/getting-started/configuration#long-question-explanation') .
//            '</bold>'
//        );
//        $this->say();

        if ($this->input->hasOption('empty') && $this->input->getOption('empty')) {
            $this->createGlobalConfig();
            $this->createDirs();
            return;
        }

        $installationData = $this->getInstallationData($interactive);

        try {
            $this->createGlobalConfig();
            $this->createDirs();
            $this->creatEnvFile($installationData);
            $this->loadEnvFile();
            $this->createSuites($installationData);
            if ($interactive) {
                $this->askToCreateDumpFile($installationData);
                $this->sayDbRedirectionSnippet($installationData);
            }
        } catch (ModuleConfigException $e) {
            $this->removeCreatedFiles();
            $this->sayError('Something is not ok in the modules configurations: check your answers and try again.');
            $this->sayError($e->getMessage());
            $this->sayInfo('All files and folders created during installation have been removed.');

            return;
        }

        $this->sayDone($interactive, $installationData);
    }

    protected function isInteractive()
    {
        $this->quiet = (bool)$this->input->getOption('quiet');
        $this->noInteraction = (bool)$this->input->getOption('no-interaction');

        if ($this->noInteraction || $this->quiet) {
            $interactive = false;
            $this->input->setInteractive(false);
        } else {
            $interactive = true;
            $this->input->setInteractive(true);
        }
        return $interactive;
    }

    protected function parseNamespace()
    {
        if ($this->input->getOption('namespace')) {
            $namespace = $this->input->getOption('namespace');
            if (is_string($namespace)) {
                $this->namespace = trim($namespace, '\\') . '\\';
            }
        }
    }

    protected function parseActor()
    {
        if ($this->input->hasOption('actor') && $this->input->getOption('actor')) {
            $actor = $this->input->getOption('actor');
            if (is_string($actor)) {
                $this->actorSuffix = $actor;
            }
        }
    }

    protected function sayWarning($message)
    {
        $this->say("<warning>$message</warning>");
    }

    protected function say($message = '')
    {
        if ($this->quiet) {
            return;
        }
        parent::say($message);
    }

    protected function sayHi()
    {
        $this->say();
        $this->sayTitle('wp-browser setup');
        $this->say();
        $this->sayInfo('by Luca Tumedei <luca@theAverageDev.com>');
        $this->sayInfo('Docs: <bold>' . docs('/') . '</bold>');
    }

    protected function sayTitle($message)
    {
        $this->say("<bold>$message</bold>");
    }

    protected function askForAcknowledgment()
    {
        $this->say();
        $this->sayWarning('This setup script can configure the test suites for you, ' .
            'but will require root access to your site database.');
        $this->sayInfo('Read more: <bold>' . docs('/getting-started/auto-configuration') . '</bold>');
        return $this->ask(
            '<warning>'
            . 'I acknowledge wp-browser should run on development servers only, '
            . 'that I have made a backup of my files and database contents before proceeding.'
            . '</warning>',
            true
        );
    }

    protected function sayBye()
    {
        $this->say();
        $this->sayInfo(
            'Setup a WordPress installation and database dedicated to development and '
            . 'restart this command when ready using `vendor/bin/codecept init wpbrowser`.'
        );
        $this->say();
        $this->saySuccess('See you soon!');
    }

    protected function saySuccess($message)
    {
        $this->say();
        $this->say("<ok>$message</ok>");
        $this->say();
    }

    public function createGlobalConfig()
    {
        $basicConfig = [
            'paths' => [
                'tests' => 'tests',
                'output' => $this->outputDir,
                'data' => $this->dataDir,
                'support' => $this->supportDir,
                'envs' => $this->envsDir,
            ],
            'actor_suffix' => 'Tester',
            'extensions' => [
                'enabled' => ['Codeception\Extension\RunFailed'],
                'commands' => $this->getAddtionalCommands(),
            ],
            'params' => [
                trim($this->envFileName),
            ],
        ];

        $str = Yaml::dump($basicConfig, 4);
        if ($this->namespace) {
            $namespace = rtrim($this->namespace, '\\');
            $str = "namespace: $namespace\n" . $str;
        }
        $this->createFile('codeception.dist.yml', $str);
    }

    protected function getAddtionalCommands()
    {
        return [
            'Codeception\\Command\\GenerateWPUnit',
            'Codeception\\Command\\GenerateWPRestApi',
            'Codeception\\Command\\GenerateWPRestController',
            'Codeception\\Command\\GenerateWPRestPostTypeController',
            'Codeception\\Command\\GenerateWPAjax',
            'Codeception\\Command\\GenerateWPCanonical',
            'Codeception\\Command\\GenerateWPXMLRPC',
        ];
    }

    /**
     * Builds, and returns, the installation data.
     *
     * @param bool $interactive Whether to build the installation data with user interactive input or not.
     *
     * @return array The installation data.
     */
    protected function getInstallationData($interactive)
    {
        if (!$interactive) {
            $installationData = [
                'acceptanceSuite' => 'acceptance',
                'functionalSuite' => 'functional',
                'wpunitSuite' => 'wpunit',
                'acceptanceSuiteSlug' => 'acceptance',
                'functionalSuiteSlug' => 'functional',
                'wpunitSuiteSlug' => 'wpunit',
                'testSiteDbHost' => 'localhost',
                'testSiteDbName' => 'wp',
                'testSiteDbUser' => 'root',
                'testSiteDbPassword' => '',
                'testSiteTablePrefix' => 'wp_',
                'testSiteWpUrl' => 'http://wp.test',
                'testSiteWpDomain' => 'wp.test',
                'testSiteAdminUsername' => 'admin',
                'testSiteAdminPassword' => 'password',
                'testSiteAdminEmail' => 'admin@wp.test',
                'testSiteWpAdminPath' => '/wp-admin',
                'wpRootFolder' => '/var/www/html',
                'testDbName' => 'wpTests',
                'testDbHost' => 'localhost',
                'testDbUser' => 'root',
                'testDbPassword' => '',
                'testTablePrefix' => 'wp_',
                'title' => 'WP Test',
                // deactivate all modules that could trigger exceptions when initialized with sudo values
                'activeModules' => ['WPDb' => false, 'WordPress' => false, 'WPLoader' => false],
            ];
            $this->envFileName = '.env.testing';
        } else {
            $installationData = $this->gatherInstallationData();
        }

        return $installationData;
    }

    protected function gatherInstallationData()
    {
        $installationData = [
            'activeModules' => [
                'WPDb' => true,
                'WPBrowser' => true,
                'WordPress' => true,
                'WPLoader' => true,
            ],
        ];

        $installationData['acceptanceSuite'] = 'acceptance';
        $installationData['functionalSuite'] = 'functional';
        $installationData['wpunitSuite'] = 'wpunit';
        $installationData['acceptanceSuiteSlug'] = 'acceptance';
        $installationData['functionalSuiteSlug'] = 'functional';
        $installationData['wpunitSuiteSlug'] = 'wpunit';
        $this->envFileName = '.env.testing';

        $this->checkEnvFileExistence();

        if ($this->installation instanceof NotFoundInstallation) {
            $this->say();
            tryTimes(function () {
                try {
                    $this->installation = new Installation(pathNormalize($this->ask(
                        'What is the WordPress root directory path (it should contain the wp-load.php file)?',
                        '/var/www/wp'
                    )));
                } catch (InstallationException $e) {
                    $this->sayError($e->getMessage());
                }
            });
            if ($this->installation instanceof Installation) {
                exit(1);
            };
        }

        $installationData['wpRootFolder'] = $this->installation->getDir('/');
        $installationData['testSiteWpAdminPath']  = $this->installation->getWpAdminPath();

        $installationData['autopilot'] = true;

        list($dbData, $siteData) = $this->fetchDbAndSiteData($installationData['wpRootFolder']);

        $installationData = array_merge($installationData, $dbData, $siteData);

        $this->sayInfo('Choose "both" if you\'re setting up  tests for a site.');
        $projectChoices = ['plugin', 'theme', 'both/site'];
        $projectType = $this->ask(
            'Are you testing a plugin, a theme or a combination of both?',
            $projectChoices
        );

        $availablePlugins = array_map(static function ($pluginFile) {
            return pathTail($pluginFile, 2);
        }, findPluginFiles($installationData['wpRootFolder']));
        $availableThemes = array_map('dirname', findThemesInDir($installationData['wpRootFolder']));

        $installationData['plugins'] = [];
        if ($projectType === 'plugin') {
            $this->sayInfo('The following answer would be "woocommerce/woocommerce.php" for the WooCommerce plugin.');
            $installationData['mainPlugin'] = $this->ask(
                'What is the <bold>folder/plugin.php</bold> name of the plugin?',
                pathTail(findPluginFile($this->workDir), 2) ?: 'my-plugin/my-plugin.php'
            );
        } else {
            if (!$this->ask('Are you developing or using a child theme?', false)) {
                $installationData['parentTheme'] = $this->ask(
                    'What is the slug of the parent theme?',
                    $availableThemes
                );
            }
            $installationData['theme'] = $this->ask('What is the slug of the theme?', $availableThemes);
        }

        $activateFurtherPlugins = $this->ask(
            'Does your project needs additional plugins to be activated to work?',
            false
        );

        if ($activateFurtherPlugins) {
            $this->sayInfo('Plugins found in the WordPress plugins directory.');
            $this->sayInfo('If a required plugin was not found, you can enter it manually.');


            $availablePlugins = array_diff($availablePlugins, [$installationData['mainPlugin']]);

            do {
                $activatePlugin = $this->ask(
                    'Please select the plugins to activate in order:',
                    array_merge($availablePlugins, ['manual', 'done'])
                );
                if ('manual' === $activatePlugin) {
                    $activatePlugin = $this->ask('Enter the <bold>dir/file.php</bold> plugin to activate:');
                }

                $installationData['plugins'] = $activatePlugin;
            } while ($activatePlugin && $activatePlugin !== 'done');
        }

        $installationData['plugins'] = !empty($installationData['plugins']) ?
            array_filter($installationData['plugins'])
            : [];
        if (!empty($installationData['mainPlugin'])) {
            $installationData['plugins'][] = $installationData['mainPlugin'];
        }

        return $installationData;
    }

    protected function checkEnvFileExistence()
    {
        $filename = $this->workDir . DIRECTORY_SEPARATOR . $this->envFileName;

        if (file_exists($filename)) {
            $basename = basename($filename);
            $message = "Found a previous {$basename} file."
                . PHP_EOL . "Remove the existing {$basename} file or specify a different name for the env file.";
            throw new RuntimeException($message);
        }
    }

    protected function askForDbData()
    {
        $data = [];

        $this->say();
        $this->sayInfo(
            'The WPDb module needs the database details to access the test database used by the test site.'
        );
        $this->say();
        $data['testSiteDbName'] = $this->ask(
            'What is the name of the test database used by the test site?',
            'wp_test_site'
        );
        $data['testSiteDbHost'] = $this->ask(
            'What is the host of the test database used by the test site?',
            'localhost'
        );
        $data['testSiteDbUser'] = $this->ask(
            'What is the user of the test database used by the test site?',
            'root'
        );
        $data['testSiteDbPassword'] = $this->ask(
            'What is the password of the test database used by the test site?',
            ''
        );
        $data['testSiteTablePrefix'] = $this->ask(
            'What is the table prefix of the test database used by the test site?',
            'wp_'
        );

        $this->say();
        $this->sayInfo(
            'WPLoader will reinstall a fresh WordPress installation before the tests.' .
            PHP_EOL . 'It needs the details you would typically provide when installing WordPress from scratch.'
        );

        $this->say();
        $this->sayWarning(implode(PHP_EOL, [
            'WPLoader should be configured to run on a dedicated database!',
            'The data stored on the database used by the WPLoader module will be lost!',
        ]));
        $this->say();

        $data['testDbName'] = $this->ask(
            'What is the name of the test database WPLoader should use?',
            'wp_test_integration'
        );
        $data['testDbHost'] = $this->ask(
            'What is the host of the test database WPLoader should use?',
            'localhost'
        );
        $data['testDbUser'] = $this->ask(
            'What is the user of the test database WPLoader should use?',
            'root'
        );
        $data['testDbPassword'] = $this->ask(
            'What is the password of the test database WPLoader should use?',
            ''
        );
        $data['testTablePrefix'] = $this->ask(
            'What is the table prefix of the test database WPLoader should use?',
            'wp_'
        );

        return $data;
    }

    protected function askForSiteData(array $installationData)
    {
        $siteData = [];
        $siteData['testSiteWpUrl'] = $this->ask(
            'What is the URL the test site?',
            'http://wp.test'
        );
        $siteData['testSiteWpUrl'] = rtrim($siteData['testSiteWpUrl'], '/');
        $url = parseUrl($siteData['testSiteWpUrl']);
        $siteData['urlScheme'] = empty($url['scheme']) ? 'http' : $url['scheme'];
        $siteData['testSiteWpDomain'] = empty($url['host']) ? 'example.com' : $url['host'];
        $siteData['urlPort'] = empty($url['port']) ? '' : ':' . $url['port'];
        $siteData['urlPath'] = empty($url['path']) ? '' : $url['path'];
        $adminEmailCandidate = "admin@{$siteData['testSiteWpDomain']}";
        $siteData['testSiteAdminEmail'] = $this->ask(
            'What is the email of the test site WordPress administrator?',
            $adminEmailCandidate
        );

        $siteData['title'] = $this->ask('What is the title of the test site?', 'Test');

        $siteData['testSiteAdminUsername'] = $this->ask(
            'What is the login of the administrator user of the test site?',
            'admin'
        );
        $siteData['testSiteAdminPassword'] = $this->ask(
            'What is the password of the administrator user of the test site?',
            'password'
        );

        return $siteData;
    }

    protected function fetchDbAndSiteData($wpRootFolder)
    {
        $this->say();

        $dbCreds = findWpDbCreds($wpRootFolder);

        if (!empty($dbCreds)) {
            $this->sayInfo('Database credentials read from wp-config.php file.');
        } else {
            $this->sayInfo('Unable to read database credentials from wp-config.php file.');
            return $this->askForDbData();
        }

        $fullDdCreds = $dbCreds;

        $db = tryDbConnection(...buildDbCredsFromWpCreds($dbCreds));

        if (!$db instanceof \PDO) {
            $this->sayError(
                sprintf(
                    "Unable to connect to the database using the credentials:%s%s",
                    PHP_EOL,
                    Yaml::dump(array_diff_key($dbCreds, ['DB_NAME' => 1, 'table_prefix' => 1]))
                )
            );
            $this->sayInfo('Possible causes: the "DB_HOST" is not correct for the machine running the tests, ' .
                'the database does not exist or the user does not have access to the database.');
            $this->sayInfo('Read more: <bold>' . docs('/getting-started/auto-configuration') . '</bold>');
            $db = $this->askForDbCreds($dbCreds, ['DB_HOST', 'DB_USER', 'DB_PASSWORD']);
        }

        if ($db === false) {
            $this->saySomethingNotWorking();
            exit(1);
        }

        $this->queryRunner = new PDOQueryRunner($db);

        $this->sayInfo(
            'Successfully connected to the database.'
        );

        $fullDdCreds = array_merge($fullDdCreds, $dbCreds);

        $dbData = $this->fetchDbData($db, $fullDdCreds);
        $siteData = $this->fetchSiteData($db, $fullDdCreds);

        $this->workingDbCreds =  $fullDdCreds;

        return [$dbData, $siteData];
    }

    protected function sayError($message)
    {
        $this->say("<error>{$message}</error>");
    }

    /**
     * Asks the user for database credentials and verifies them.
     *
     * @param array $dbCreds The credentials to use, passed by reference and modified during execution.
     * @param array $mask A mask of the credentials to ask.
     *
     * @return \PDO A PDO connection handle. If the user provides wrong credentials the method will exit.
     */
    protected function askForDbCreds(array &$dbCreds = [], array $mask = [])
    {
        $db = false;
        $retries = 0;
        $tryCreds = $dbCreds;
        $intersectMask = array_combine($mask, $mask);
        if ($intersectMask === false) {
            $intersectMask = [];
        }

        do {
            if ($retries === 3) {
                $this->say();
                $this->sayWarning('Something is not working, check the connections, credentials and try to run this ' .
                    'initialization script again.');
                exit(1);
            }

            if ($retries !== 0) {
                $this->sayError(
                    sprintf(
                        'Could not connect to the database using these credentials:%s%s',
                        PHP_EOL,
                        Yaml::dump($tryCreds)
                    )
                );
            }

            $creds = [
                'DB_HOST' => 'What is the database host (incl. the :port)?',
                'DB_NAME' => 'What is the database name?',
                'DB_USER' => 'What is the database user?',
                'DB_PASSWORD' => 'What is the database password?'
            ];
            foreach ($creds as $var => $question) {
                ${$var} = isset($dbCreds[$var]) ? $dbCreds[$var] : '';
                if (empty($mask) || in_array($var, $mask, true)) {
                    ${$var} = $this->ask($question, ${$var});
                }
            }
            /** @noinspection CompactArgumentsInspection */
            $dbCreds = compact('DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASSWORD');
            $tryCreds = $mask ?
                array_intersect_key($dbCreds, $intersectMask)
                : $dbCreds;
        } while ($retries++ < 3
        && !($db = tryDbConnection(...buildDbCredsFromWpCreds($tryCreds))) instanceof \PDO
        );

        if ($db === false) {
            $this->saySomethingNotWorking();
            exit(1);
        }

        return $db;
    }

    protected function saySomethingNotWorking()
    {
        $this->say();
        $this->sayWarning('Something is not working, check the connections, credentials and try to run this ' .
            'initialization script again.');
        $this->say();
    }

    protected function fetchDbData(\PDO $db, array &$dbCreds)
    {
        $data = [];
        $tablePrefix = $dbCreds['table_prefix'];

        list($testSiteDbName, $testDbName) = $this->tryCreateDb($db, $dbCreds);

        $data['testSiteDbName'] = $testSiteDbName;
        $data['testSiteDbHost'] = $dbCreds['DB_HOST'];
        $data['testSiteDbUser'] = $dbCreds['DB_USER'];
        $data['testSiteDbPassword'] = $dbCreds['DB_PASSWORD'];
        $data['testSiteTablePrefix'] = $tablePrefix;
        $data['testDbName'] = $testDbName;
        $data['testDbHost'] = $dbCreds['DB_HOST'];
        $data['testDbUser'] = $dbCreds['DB_USER'];
        $data['testDbPassword'] = $dbCreds['DB_PASSWORD'];
        $data['testTablePrefix'] = $tablePrefix;

        return $data;
    }

    protected function tryCreateDb(\PDO $db, array &$dbCreds)
    {
        $testSiteDbName = slug(pathJoin($this->projectName, 'site_tests'), '_');
        $testDbName = slug(pathJoin($this->projectName, 'tests'), '_');

        $createdSiteTestDb = $this->tryQuery("CREATE DATABASE IF NOT EXISTS {$testSiteDbName}", [], $dbCreds);
        $createdTestDb = $this->tryQuery("CREATE DATABASE IF NOT EXISTS {$testDbName}", [], $dbCreds);

        if (!($createdSiteTestDb instanceof \PDOStatement && $createdTestDb instanceof \PDOStatement)) {
            $this->saySomethingNotWorking();
            exit(1);
        }

        $this->saySuccess(sprintf('Created the "%s" and "%s" databases.', $testSiteDbName, $testDbName));

        return [$testSiteDbName, $testDbName,];
    }

    protected function tryQuery($query, array $args, array &$dbCreds)
    {
        $retry = function (\PDO $pdo, $statement) use (&$dbCreds, $query) {
            $this->sayWarning('Unable to run this query: ' . $query);
            if ($statement instanceof \PDOStatement) {
                $errorInfo = $statement->errorInfo();
                if (isset($errorInfo[2])) {
                    $this->sayInfo('Error: ' . $errorInfo[2]);
                }
            }
            $this->sayInfo('The specified database user might not have the required privileges;' .
                ' please specify a user with all privileges on the database.');
            return $this->askForDbCreds($dbCreds, ['DB_HOST', 'DB_USER', 'DB_PASSWORD']);
        };
        $fail = function () {
            $this->saySomethingNotWorking();
            exit(1);
        };

        return $this->queryRunner->run($query, $args, $retry, $fail);
    }

    protected function fetchSiteData(\PDO $db, array &$dbCreds)
    {
        $siteData = [];

        $useSiteDbStmt = $this->tryQuery("USE {$dbCreds['DB_NAME']}", [], $dbCreds);

        if ($useSiteDbStmt === false) {
            $this->sayWarning('Cannot fetch the value of the "siteurl" option from the database.');
            $this->sayWarning('Make sure WordPress is installed and working correctly and run this script again.');
            exit(1);
        }

        $options = $dbCreds['table_prefix'] . 'options';
        $testSiteWPUrlStmt = $this->tryQuery(
            "SELECT option_value FROM {$options} WHERE option_name = ?",
            ['siteurl'],
            $dbCreds
        );

        $testSiteWPUrl = $testSiteWPUrlStmt->fetchColumn();

        if (empty($testSiteWPUrl)) {
            $this->sayWarning('Cannot fetch the value of the "siteurl" option from the database.');
            $this->sayWarning('Make sure WordPress is installed and working correctly and run this script again.');
            exit(1);
        }

        $siteData['testSiteWpUrl'] = $testSiteWPUrl;
        $siteData['testSiteWpUrl'] = rtrim($siteData['testSiteWpUrl'], '/');
        $url = parseUrl($siteData['testSiteWpUrl']);
        $siteData['urlScheme'] = empty($url['scheme']) ? 'http' : $url['scheme'];
        $siteData['testSiteWpDomain'] = empty($url['host']) ? 'example.com' : $url['host'];
        $siteData['urlPort'] = empty($url['port']) ? '' : ':' . $url['port'];
        $siteData['urlPath'] = empty($url['path']) ? '' : $url['path'];
        $adminEmailCandidate = "admin@{$siteData['testSiteWpDomain']}";
        $siteData['testSiteAdminEmail'] = filter_var($adminEmailCandidate, FILTER_VALIDATE_EMAIL) ?
            $adminEmailCandidate
            : $this->ask('What is the email of the test site WordPress administrator?');

        $siteData['title'] = $this->projectName . ' test';

        $usermeta = $dbCreds['table_prefix'] . 'usermeta';
        $testSiteAdminIdStmt = $this->tryQuery(
            "SELECT user_id FROM {$usermeta} WHERE meta_key = ? and meta_value like ?;",
            ['wp_capabilities', '%s:13:"administrator";%'],
            $dbCreds
        );

        if (empty($testSiteAdminIdStmt)) {
            $this->sayWarning('Cannot read the admin user details from the database.');
            $this->sayWarning('Make sure WordPress is installed and working correctly and run this script again.');
            exit(1);
        }

        $testSiteAdminUserId = $testSiteAdminIdStmt->fetchColumn();

        $users = $dbCreds['table_prefix'] . 'users';
        $testSiteAdminUsernameStmt = $this->tryQuery(
            "SELECT user_login FROM {$users} WHERE ID = ?;",
            [(int)$testSiteAdminUserId],
            $dbCreds
        );

        if (empty($testSiteAdminUsernameStmt)) {
            $this->sayWarning('Cannot read the admin user details from the database.');
            $this->sayWarning('Make sure WordPress is installed and working correctly and run this script again.');
            exit(1);
        }

        $installation->haveUserInDatabase('test_admin', 'administrator', 'password');

        $testSiteAdminUsername = $testSiteAdminUsernameStmt->fetchColumn();

        if (empty($testSiteWPUrl)) {
            $this->sayWarning('Cannot fetch the administrator user information from the database.');

            $testSiteAdminUsername = $this->ask(
                'What is the login of the administrator user of the test site?',
                'admin'
            );

            $testSiteAdminPassword = $this->ask(
                'What is the password of the administrator user of the test site?',
                'password'
            );
        } else {
            $testSiteAdminPassword = $this->ask(
                sprintf(
                    'What is the password of the "%s" user of the test site?',
                    $testSiteAdminUsername
                ),
                'password'
            );
        }

        $siteData['testSiteAdminUsername'] = $testSiteAdminUsername;
        $siteData['testSiteAdminPassword'] = $testSiteAdminPassword;

        return $siteData;
    }

    /**
     * Creates, writing it to disk, the environment (.env).
     *
     * @param array $installationData The installation data.
     */
    protected function creatEnvFile(array $installationData = [])
    {
        $filename = $this->workDir . DIRECTORY_SEPARATOR . $this->envFileName;

        $envKeys = [
            'testSiteDbHost' => true,
            'testSiteDbName' => true,
            'testSiteDbUser' => true,
            'testSiteDbPassword' => true,
            'testSiteTablePrefix' => true,
            'testSiteWpUrl' => true,
            'testSiteAdminUsername' => true,
            'testSiteAdminPassword' => true,
            'testSiteWpAdminPath' => true,
            'wpRootFolder' => true,
            'testDbName' => true,
            'testDbHost' => true,
            'testDbUser' => true,
            'testDbPassword' => true,
            'testTablePrefix' => true,
            'testSiteWpDomain' => true,
            'testSiteAdminEmail' => true,
        ];

        $envEntries = array_intersect_key($installationData, $envKeys);

        $envFileLines = [];

        foreach ($envEntries as $key => $value) {
            $key = strtoupper(slug($key, '_'));
            if (is_bool($value)) {
                $value ? 'true' : 'false';
            } elseif (null === $value) {
                $value = 'null';
            } else {
                $value = '"' . trim($value) . '"';
            }
            $envFileLines[] = "{$key}={$value}";
        }
        $envFileContents = implode("\n", $envFileLines);
        $written = file_put_contents($filename, $envFileContents);
        if (!$written) {
            $this->removeCreatedFiles();
            throw new RuntimeException("Could not write {$this->envFileName} file!");
        }
    }

    protected function removeCreatedFiles()
    {
        $files = ['codeception.yml', $this->envFileName];
        $dirs = ['tests'];
        foreach (array_filter($files) as $file) {
            if (file_exists(getcwd() . '/' . $file)) {
                unlink(getcwd() . '/' . $file);
            }
        }
        foreach (array_filter($dirs) as $dir) {
            if (file_exists(getcwd() . '/' . $dir)) {
                rrmdir(getcwd() . '/' . $dir);
            }
        }
    }

    protected function loadEnvFile()
    {
        $dotEnv = Dotenv::create($this->workDir, $this->envFileName);
        $dotEnv->load();
    }

    /**
     * ${CARET}
     *
     * @param array $installationData
     * @since TBD
     *
     */
    protected function createSuites(array $installationData)
    {
        $this->say();
        $this->createUnitSuite();
        $this->createWpUnitSuite(ucwords($installationData['wpunitSuite']), $installationData);
        $this->createFunctionalSuite(ucwords($installationData['functionalSuite']), $installationData);
        $this->createAcceptanceSuite(ucwords($installationData['acceptanceSuite']), $installationData);
        $this->saySuccess('All ready to test your WordPress project.');
    }

    protected function createWpUnitSuite($actor = 'Wpunit', array $installationData = [])
    {
        $installationData = new Data($installationData);
        $WPLoader = !empty($installationData['activeModules']['WPLoader']) ? '- WPLoader' : '# - WPLoader';
        $suiteConfig = <<<EOF
# Codeception Test Suite Configuration
#
# Suite for unit or integration tests that require WordPress functions and classes.

actor: $actor{$this->actorSuffix}
modules:
    enabled:
        {$WPLoader}
        - \\{$this->namespace}Helper\\$actor
    config:
        WPLoader:
            wpRootFolder: "%WP_ROOT_FOLDER%"
            dbName: "%TEST_DB_NAME%"
            dbHost: "%TEST_DB_HOST%"
            dbUser: "%TEST_DB_USER%"
            dbPassword: "%TEST_DB_PASSWORD%"
            tablePrefix: "%TEST_TABLE_PREFIX%"
            domain: "%TEST_SITE_WP_DOMAIN%"
            adminEmail: "%TEST_SITE_ADMIN_EMAIL%"
            title: "{$installationData['title']}"
EOF;

        if (!empty($installationData['theme'])) {
            $theme = empty($installationData['parentTheme']) ?
                $installationData['theme']
                : "[{$installationData['parentTheme']}, {$installationData['theme']}]";
            $suiteConfig .= <<<EOF
            
            theme: {$theme}
EOF;
        }

        $plugins = $installationData['plugins'];
        $plugins = "'" . implode("', '", (array)$plugins) . "'";
        $suiteConfig .= <<< EOF
        
            plugins: [{$plugins}]
            activatePlugins: [{$plugins}]
EOF;

        $this->createSuite($installationData['wpunitSuiteSlug'], $actor, $suiteConfig);
    }

    protected function createFunctionalSuite($actor = 'Functional', array $installationData = [])
    {
        $installationData = new Data($installationData);
        $WPDb = !empty($installationData['activeModules']['WPDb']) ? '- WPDb' : '# - WPDb';
        $WPBrowser = !empty($installationData['activeModules']['WPBrowser']) ? '- WPBrowser' : '# - WPBrowser';
        $WPFilesystem = !empty($installationData['activeModules']['WPFilesystem']) ?
            '- WPFilesystem'
            : '# - WPFilesystem';
        $suiteConfig = <<<EOF
# Codeception Test Suite Configuration
#
# Suite for {$installationData['functionalSuiteSlug']} tests
# Emulate web requests and make WordPress process them

actor: $actor{$this->actorSuffix}
modules:
    enabled:
        {$WPDb}
        {$WPBrowser}
        {$WPFilesystem}
        - Asserts
        - \\{$this->namespace}Helper\\{$actor}
    config:
        WPDb:
            dsn: 'mysql:host=%TEST_SITE_DB_HOST%;dbname=%TEST_SITE_DB_NAME%'
            user: '%TEST_SITE_DB_USER%'
            password: '%TEST_SITE_DB_PASSWORD%'
            dump: 'tests/_data/dump.sql'
            populate: true
            cleanup: true
            waitlock: 10
            url: '%TEST_SITE_WP_URL%'
            urlReplacement: true
            tablePrefix: '%TEST_SITE_TABLE_PREFIX%'
        WPBrowser:
            url: '%TEST_SITE_WP_URL%'
            adminUsername: '%TEST_SITE_ADMIN_USERNAME%'
            adminPassword: '%TEST_SITE_ADMIN_PASSWORD%'
            adminPath: '%TEST_SITE_WP_ADMIN_PATH%'
            headers:
                X_TEST_REQUEST: 1
                X_WPBROWSER_REQUEST: 1
        WPFilesystem:
            wpRootFolder: '%WP_ROOT_FOLDER%'
            plugins: '/wp-content/plugins'
            mu-plugins: '/wp-content/mu-plugins'
            themes: '/wp-content/themes'
            uploads: '/wp-content/uploads'
EOF;
        $this->createSuite($installationData['functionalSuiteSlug'], $actor, $suiteConfig);
    }

    protected function createAcceptanceSuite($actor = 'Acceptance', array $installationData = [])
    {
        $installationData = new Data($installationData);
        $WPDb = !empty($installationData['activeModules']['WPDb']) ? '- WPDb' : '# - WPDb';
        $WPBrowser = !empty($installationData['activeModules']['WPBrowser']) ? '- WPBrowser' : '# - WPBrowser';
        $suiteConfig = <<<EOF
# Codeception Test Suite Configuration
#
# Suite for {$installationData['acceptanceSuiteSlug']} tests.
# Perform tests in browser using the WPWebDriver or WPBrowser.
# Use WPDb to set up your initial database fixture.
# If you need both WPWebDriver and WPBrowser tests - create a separate suite.

actor: $actor{$this->actorSuffix}
modules:
    enabled:
        {$WPDb}
        {$WPBrowser}
        - \\{$this->namespace}Helper\\{$actor}
    config:
        WPDb:
            dsn: 'mysql:host=%TEST_SITE_DB_HOST%;dbname=%TEST_SITE_DB_NAME%'
            user: '%TEST_SITE_DB_USER%'
            password: '%TEST_SITE_DB_PASSWORD%'
            dump: 'tests/_data/dump.sql'
            #import the dump before the tests; this means the test site database will be repopulated before the tests.
            populate: true 
            # re-import the dump between tests; this means the test site database will be repopulated between the tests.
            cleanup: true 
            waitlock: 10
            url: '%TEST_SITE_WP_URL%'
            urlReplacement: true #replace the hardcoded dump URL with the one above
            tablePrefix: '%TEST_SITE_TABLE_PREFIX%'
        WPBrowser:
            url: '%TEST_SITE_WP_URL%'
            adminUsername: '%TEST_SITE_ADMIN_USERNAME%'
            adminPassword: '%TEST_SITE_ADMIN_PASSWORD%'
            adminPath: '%TEST_SITE_WP_ADMIN_PATH%'
            headers:
                X_TEST_REQUEST: 1
                X_WPBROWSER_REQUEST: 1
EOF;
        $this->createSuite($installationData['acceptanceSuiteSlug'], $actor, $suiteConfig);
    }

    protected function sayDone($interactive, array $installationData)
    {
        if (!$interactive) {
            $this->saySuccess("Codeception has created the files for the {$installationData['acceptanceSuiteSlug']}, "
                . "{$installationData['functionalSuiteSlug']}, WordPress unit and unit suites "
                . 'but the modules are not activated.');
        }

        $this->sayInfo('Some commands have been added in the Codeception configuration file: '
            . 'check them out using <bold>codecept --help</bold>');
        $this->say();

        $autopilot = !empty($installationData['autopilot']);

        $this->say("<bold>Next steps:</bold>");
        if (!$autopilot) {
            $this->say('0. <bold>Create the databases used by the modules</bold>; wp-browser will not do it for you!');
            $this->say('1. <bold>Install and configure WordPress</bold> activating the theme and plugins you need to create'
                . ' a database dump in <bold>tests/_data/dump.sql</bold>');
            $this->say("2. Edit <bold>tests/{$installationData['acceptanceSuiteSlug']}.suite.yml</bold> to make sure WPDb "
                . 'and WPBrowser configurations match your local setup; change WPBrowser to WPWebDriver to '
                . 'enable browser testing');
            $this->say("3. Edit <bold>tests/{$installationData['functionalSuiteSlug']}.suite.yml</bold> to make sure "
                . 'WordPress and WPDb configurations match your local setup');
            $this->say("4. Edit <bold>tests/{$installationData['wpunitSuiteSlug']}.suite.yml</bold> to make sure WPLoader "
                . 'configuration matches your local setup');
            $this->say("5. Create your first {$installationData['acceptanceSuiteSlug']} tests using <bold>codecept "
                . "g:cest {$installationData['acceptanceSuiteSlug']} WPFirst</bold>");
            $this->say("6. Write a test in <bold>tests/{$installationData['acceptanceSuiteSlug']}/WPFirstCest.php</bold>");
            $this->say("7. Run tests using: <bold>codecept run {$installationData['acceptanceSuiteSlug']}</bold>");
        } else {
            $this->say("1. Create your first {$installationData['acceptanceSuiteSlug']} tests using <bold>codecept "
                . "g:cest {$installationData['acceptanceSuiteSlug']} WPFirst</bold>");
            $this->say("2. Write a test in <bold>tests/{$installationData['acceptanceSuiteSlug']}/WPFirstCest.php</bold>");
            $this->say("3. Run tests using: <bold>codecept run {$installationData['acceptanceSuiteSlug']}</bold>");
        }

        $this->say();
        $this->sayInfo('Note: <bold>due to WordPress reliance on globals and constants you should not run all the ' .
            'suites at the same time!</bold>');
        $this->sayInfo('Run each suite separately, like this: <bold>codecept run unit && codecept run '
            . "{$installationData['wpunitSuiteSlug']}</bold>");
    }

    /**
     * Sets the template working directory.
     *
     * @param string $workDir The path to the working directory the template should use.
     */
    public function setWorkDir($workDir)
    {
        chdir($workDir);
    }

    /**
     * On destruction remove the created files if the command did not complete correctly.
     */
    public function __destruct()
    {
        $this->removeCreatedFiles();
    }

    protected function getDefaultInstallationData()
    {
        return [];
    }

    protected function parseEnvName()
    {
        $envFileName = trim($this->envFileName);
        if (strpos($envFileName, '.env') !== 0) {
            $message = 'Please specify an env file name starting with ".env", e.g. ".env.testing" or '
                . '".env.development"';
            throw new RuntimeException($message);
        }
    }

    protected function askMultiselectQuestion($question, array $answer)
    {
        $question = "? $question";
        $dialog = new QuestionHelper();
        $choiceQuestion = new ChoiceQuestion($question, $answer, 0);
        $choiceQuestion->setMultiselect(true);
        $dialog->ask($this->input, $this->output, $choiceQuestion);
    }

    protected function askToCreateDumpFile(array $installationData)
    {
        $askToCreateDbDump = function () use ($installationData) {
            return $this->ask('Would you like to create the database dump for the tests now?', true)
                ? $installationData : false;
        };

        $dumpFile = codecept_root_dir('db-backup.sql');

        $dumpDbToFile = function (array $installationData, $dumpFile) {
            $dumped = dumpToFile(buildDbCredsFromWpCreds($this->workingDbCreds), $dumpFile);

            if (false === $dumped) {
                $this->sayError('Something went wrong while trying to dump the current database.');
                return false;
            }

            return $installationData;
        };


        $this->testDbCreds = $this->workingDbCreds;
        $this->testDbCreds['DB_NAME'] = $installationData['testSiteDbName'];

        $cloneDb = function (array $installationData, $dumpFile) {
            $imported = importDump(buildDbCredsFromWpCreds($this->testDbCreds), $dumpFile);

            if (false === $imported) {
                $this->sayError('Something went wrong while trying to clone the current database.');
                return false;
            }

            return $installationData;
        };

        $dbDropIn = pathJoin(getWpContentDir($installationData['wpRootFolder']), '/db.php');

        $placeDbDropin = function (array $installationData, $dbDropIn) {
            return putFileReplacement(
                $dbDropIn,
                dbDropInForEnv(),
                function ($message) {
                    $this->sayError('Error while creating the wp-db drop-in file: ' . $message);
                }
            ) ? $installationData : false;
        };

        $prepareDump = function (array $installationData) {
            $this->setUpWpCli($installationData['wpRootFolder']);

            $testDbEnv = [
                'WP_DB_USER' => $this->testDbCreds['DB_USER'],
                'WP_DB_HOST' => $this->testDbCreds['DB_HOST'],
                'WP_DB_NAME' => $this->testDbCreds['DB_NAME'],
                'WP_DB_PASSWORD' => $this->testDbCreds['DB_PASSWORD'],
            ];

            $plugins = $installationData['plugins'];
            $commands = [
                // Empty the site.
                ['site', 'empty', '--yes', '--uploads'],
                // Activate plugins, if required.
                count($plugins) ? array_merge(['plugin', 'activate'], $plugins) : false,
                // Activate themes, if  required.
                !empty($installationData['theme']) ? ['theme', 'activate', $installationData['theme']] : false,
                // Flush rewrites.
                ['rewrite', 'flush'],
            ];

            foreach (array_filter($commands) as $command) {
                try {
                    $this->sayInfo('Running command: wp ' . implode(' ', $command));
                    $commandProcess = $this->executeWpCliCommand($command, 120, $testDbEnv);
                } catch (WpCliException $e) {
                    return false;
                }
                if ($commandProcess->getExitCode() !== 0) {
                    $this->sayError('Error: ' . $commandProcess->getErrorOutput());
                    return false;
                }
            }

            return $installationData;
        };

        $dumpTestDbToFile = function (array $installationData) {
            $this->sayInfo('Dumping database to file.');
            $dumpFile = pathJoin($this->workDir, 'tests/_data/dump.sql');
            return dumpToFile(buildDbCredsFromWpCreds($this->testDbCreds), $dumpFile) ? $installationData : false;
        };

        $removeDbDropin = function (array $installationData, $dbDropIn) {
            return renameFile(
                $dbDropIn. '.bak',
                $dbDropIn,
                function ($message) {
                    $this->sayError('Error while restoring the wp-db drop-in file: ' . $message);
                }
            ) ? $installationData : false;
        };

        $saySuccess = function () {
            $this->saySuccess('Database dump created.');
            return false;
        };

        $sayFailed = function () {
            $this->sayWarning('You will need to create a database dump manually and save it to "tests/_data/dump.sql"' .
                ' before running the tests!');

            // @todo write this!
            $this->sayInfo('Read more: <bold>' . docs('/getting-started/initial-database-dump') . '</bold>');
        };

        Maybe::create($this->workingDbCreds)
            ->bind($askToCreateDbDump)
            ->bind($dumpDbToFile, [$dumpFile])
            ->bind($cloneDb, [$dumpFile])
            ->bind($placeDbDropin, [$dbDropIn])
            ->bind($prepareDump)
            ->bind($dumpTestDbToFile)
            ->bind($removeDbDropin, [$dbDropIn])
            ->then($saySuccess, $sayFailed);
    }


    protected function sayDbRedirectionSnippet(array $installationData)
    {
        $wpRootDir = $installationData['wpRootFolder'];
        $wpConfigFile = findWpConfigFile($wpRootDir);
        $wpConfigContents = file_get_contents($wpConfigFile);
        $testDbName = $this->testDbCreds['DB_NAME'];
        $dbName = getWpConfigConstant($wpRootDir, 'DB_NAME');
        $replacementSnippet = <<< PHP
if( isset( \$_SERVER['HTTP_X_TEST_REQUEST'] ) && \$_SERVER['HTTP_X_TEST_REQUEST'] ) {
      define( 'DB_NAME', '{$testDbName}' );
} else {
      define( 'DB_NAME', '{$dbName}' );
}
PHP;

        $replaceDbNameLine = function ($wpConfigFile) use ($wpConfigContents, $replacementSnippet) {
            $replaced = preg_replace(
                '/^define\\s*\\(\\s*(\'|")DB_NAME(\'|")\\s*,.*$/um',
                $replacementSnippet,
                $wpConfigContents
            );

            return $replaced !== $wpConfigContents && file_put_contents($wpConfigFile, $replaced, LOCK_EX);
        };

        $saySucces =function () {
            $this->saySuccess('DB_NAME constant line replaced in wp-config.php file.');
        };

        $suggestEdit = function () use ($wpConfigContents, $replacementSnippet, $wpConfigFile) {
            $this->sayWarning('Unable to automatically replace the DB_NAME line in the wp-config.php file.');
            // @todo write this!
            $this->sayInfo('Read more: <bold>'
                . docs('/getting-started/auto-configuration#wp-config-update') . '</bold>');
            $this->sayInfo('Here is the snippet of code you should insert in your wp-config.php file in place of the ' .
                'line that defines the "DB_NAME" constant.');

            $this->sayWarning("Start copying below this line ---\n");
            $this->say($replacementSnippet);
            $this->sayWarning("\nStop copying above this line ---");

            $this->ask('Press enter when you are ready to edit the wp-config.php file...', true);

            Maybe::create(launch_editor_for_input($wpConfigContents, 'wp-config.php'))
                ->bind(static function ($wpConfigContent) use ($wpConfigFile) {
                    return file_put_contents($wpConfigFile, $wpConfigContent, LOCK_EX);
                })
                ->then(function () {
                    $this->saySuccess('wp-config.php file updated.');
                }, function () {
                    $this->sayError('Unable to update the wp-config.php file: please do it manually before ' .
                        'running the tests.');
                });
        };

        Maybe::create($wpConfigFile)
            ->bind($replaceDbNameLine)
            ->then($saySucces, $suggestEdit);
    }

    /**
     * Finds the WordPress root directory path either by "looking around" or by asking the user.
     *
     * Relative paths are resolved to absolute paths.
     *
     * @return string The WordPress installation root directory path.
     */
    protected function findWpRootDir()
    {
        $wpRootDir = findWordPressRootDir(codecept_root_dir(), false);
        if (false === $wpRootDir) {
            $this->say();
            do {
                $wpRootDir = realpath(pathNormalize($this->ask(
                    'What is the WordPress root directory path (it should contain the wp-load.php file)?',
                    '/var/www/wp'
                )));
                if (!$wpRootDir) {
                    $this->sayError('This path does not exist or is not a directory.');
                }
            } while (!$wpRootDir);
        }

        return $wpRootDir;
    }
}
