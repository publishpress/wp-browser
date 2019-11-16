<?php /** @noinspection PhpComposerExtensionStubsInspection */

namespace Codeception\Template;

use Codeception\Exception\ModuleConfigException;
use Dotenv\Dotenv;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Yaml\Yaml;
use tad\WPBrowser\Template\Data;
use tad\WPBrowser\Traits\WithCustomCliColors;
use function tad\WPBrowser\buildDbCredsFromWpCreds;
use function tad\WPBrowser\docs;
use function tad\WPBrowser\findWordPressRootDir;
use function tad\WPBrowser\findWpDbCreds;
use function tad\WPBrowser\normalizePath;
use function tad\WPBrowser\parseUrl;
use function tad\WPBrowser\pathJoin;
use function tad\WPBrowser\slug;
use function tad\WPBrowser\tryDbConnection;
use function tad\WPBrowser\version;

class Wpbrowser extends Bootstrap
{
    use WithCustomCliColors;

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

    public function setup()
    {
        // @todo update configuration to new flow.

        $this->customizeOutputColors($this->output, 'cold');
        /** @noinspection PhpUnhandledExceptionInspection */
        $this->checkInstalled($this->workDir);
        $interactive = $this->isInteractive();
        $this->parseNamespace();
        $this->parseActor();

        $workDir = realpath($this->workDir);
        $this->projectName = $workDir ?
            slug(dirname($workDir), '_') :
            $this->ask('What is the project name?', 'project');

        if ($interactive) {
            $this->sayHi();
            $this->askForAcknowledgmentOrExit();
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
        } catch (ModuleConfigException $e) {
            $this->removeCreatedFiles();
            $this->sayError('Something is not ok in the modules configurations: check your answers and try again.');
            $this->sayError($e->getMessage());
            $this->sayInfo('All files and folders created during installation have been removed.');

            return;
        }

        $this->sayDone($interactive, $installationData);
    }

    protected function askForAcknowledgmentOrExit()
    {
        $this->say();
        $acknowledge = $this->ask(
            '<warning>'
            . 'I acknowledge wp-browser should run on development servers only, '
            . 'that I have made a backup of my files and database contents before proceeding.'
            . '</warning>',
            true
        );

        // @todo move this to later.
//        $this->say();
//        $this->sayInfo('If you want to automatically use a test database during acceptance and functional tests, '
//            . 'read here:');
//        $this->sayInfo('<bold>' . docs('/tutorials/using-diff-db-in-tests') . '</bold>');

        if (!$acknowledge) {
            $this->sayBye();
            exit(0);
        }
    }

    protected function say($message = '')
    {
        if ($this->quiet) {
            return;
        }
        parent::say($message);
    }

    protected function saySuccess($message)
    {
        $this->say("<ok>$message</ok>");
    }

    protected function sayTitle($message)
    {
        $this->say("<bold>$message</bold>");
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
            $installationData = $this->askForInstallationData();
        }

        return $installationData;
    }

    protected function askForInstallationData()
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
        $this->envFileName = '.env.testing';

        $this->checkEnvFileExistence();

        $this->say();
        $installationData['wpRootFolder'] = normalizePath($this->ask(
            'What is the WordPress root directory path (it should contain the wp-load.php file)?',
            findWordPressRootDir(codecept_root_dir(), '/var/www/wp')
        ));

        if (is_dir(pathJoin($installationData['wpRootFolder'], '/wp-admin'))) {
            $installationData['testSiteWpAdminPath'] = '/wp-admin';
        } else {
            $installationData['testSiteWpAdminPath'] = $this->ask(
                'What is the path, relative to WordPress root URL, of the admin area of the test site?',
                '/wp-admin'
            );
        }
        $normalizedAdminPath = trim(normalizePath($installationData['testSiteWpAdminPath']), '/');
        $installationData['testSiteWpAdminPath'] = '/' . $normalizedAdminPath;

        $this->say();
        $this->sayInfo(
            'To setup the database for you this script requires full access to the database (as "root" user).'
        );
        $this->sayInfo('You can answer "no" to provide the information manually.');
        // @todo create the /getting-started/auto-configuration doc
        $this->sayInfo('Read more: <bold>'.docs('/getting-started/auto-configuration').'</bold>');

        $allowRoot = $this->ask(
            '<warning>'
            . 'Do you want to allow this script to access your database with all privileges?'
            . '</warning>',
            true
        );

        if (!$allowRoot) {
            $dbData = $this->askForDbData();
        } else {
            $dbData = $this->handleDbData($installationData['wpRootFolder']);
        }

        $installationData = array_merge($installationData, $dbData);

        $installationData['testSiteWpUrl'] = $this->ask(
            'What is the URL the test site?',
            'http://wp.test'
        );
        $installationData['testSiteWpUrl'] = rtrim($installationData['testSiteWpUrl'], '/');
        $url = parseUrl($installationData['testSiteWpUrl']);
        $installationData['urlScheme'] = empty($url['scheme']) ? 'http' : $url['scheme'];
        $installationData['testSiteWpDomain'] = empty($url['host']) ? 'example.com' : $url['host'];
        $installationData['urlPort'] = empty($url['port']) ? '' : ':' . $url['port'];
        $installationData['urlPath'] = empty($url['path']) ? '' : $url['path'];
        $adminEmailCandidate = "admin@{$installationData['testSiteWpDomain']}";
        $installationData['testSiteAdminEmail'] = $this->ask(
            'What is the email of the test site WordPress administrator?',
            $adminEmailCandidate
        );
        $installationData['title'] = $this->ask('What is the title of the test site?', 'Test');
        $installationData['testSiteAdminUsername'] = $this->ask(
            'What is the login of the administrator user of the test site?',
            'admin'
        );
        $installationData['testSiteAdminPassword'] = $this->ask(
            'What is the password of the administrator user of the test site?',
            'password'
        );

        $sut = '';

        while (!in_array($sut, ['plugin', 'theme', 'both'])) {
            $sut = $this->ask('Are you testing a plugin, a theme or a combination of both (both)?', 'plugin');
        }

        $installationData['plugins'] = [];
        if ($sut === 'plugin') {
            $installationData['mainPlugin'] = $this->ask(
                'What is the <comment>folder/plugin.php</comment> name of the plugin?',
                'my-plugin/my-plugin.php'
            );
        } elseif ($sut === 'theme') {
            $isChildTheme = $this->ask('Are you developing a child theme?', 'no');
            if (preg_match('/^(y|Y)/', $isChildTheme)) {
                $installationData['parentTheme'] = $this->ask(
                    'What is the slug of the parent theme?',
                    'twentyseventeen'
                );
            }
            $installationData['theme'] = $this->ask('What is the slug of the theme?', 'my-theme');
        } else {
            $isChildTheme = $this->ask('Are you using a child theme?', 'no');
            if (preg_match('/^(y|Y)/', $isChildTheme)) {
                $installationData['parentTheme'] = $this->ask(
                    'What is the slug of the parent theme?',
                    'twentyseventeen'
                );
            }
            $installationData['theme'] = $this->ask('What is the slug of the theme you are using?', 'my-theme');
        }

        $activateFurtherPlugins = $this->ask(
            'Does your project needs additional plugins to be activated to work?',
            'no'
        );

        if (preg_match('/^(y|Y)/', $activateFurtherPlugins)) {
            do {
                $plugin = $this->ask(
                    'Please enter the plugin <comment>folder/plugin.php</comment> (leave blank when done)',
                    ''
                );
                $installationData['plugins'][] = $plugin;
            } while (!empty($plugin));
        }

        $installationData['plugins'] = array_filter($installationData['plugins']);
        if (!empty($installationData['mainPlugin'])) {
            $installationData['plugins'] = $installationData['mainPlugin'];
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

    protected function sayWarning($message)
    {
        $this->say("<warning>$message</warning>");
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

    protected function handleDbData($wpRootFolder)
    {
        $this->say();

        $dbCreds = findWpDbCreds($wpRootFolder);

        if (!empty($dbCreds)) {
            $this->sayInfo('Database credentials read from wp-config.php file.');
        } else {
            $this->sayInfo('Unable to read database credentials from wp-config.php file.');
            return $this->askForDbData();
        }

        $db = tryDbConnection(...buildDbCredsFromWpCreds($dbCreds));

        if (!$db instanceof \PDO) {
            $this->sayWarning(
                sprintf(
                    "Unable to connect to the database using the credentials:%s%s",
                    PHP_EOL,
                    Yaml::dump(array_diff_key($dbCreds, ['DB_NAME'=>1,'table_prefix'=>1]))
                )
            );
            $this->sayInfo('Possible causes: the "DB_HOST" is not correct for the machine running the tests, ' .
                'the database does not exist or the user does not have access to the database.');
            $this->sayInfo('Read more: <bold>'.docs('/getting-started/auto-configuration').'</bold>');
            if ($this->ask('Would you like to try using different credentials?', true)) {
                $db = $this->askForDbCreds($dbCreds, ['DB_HOST','DB_USER','DB_PASSWORD']);
            }
        }

        $this->sayInfo(
            'Successfully connected to the database using the credentials found in the wp-config.php file.'
        );

        $testDb = $this->tryCreateDb($db, $dbCreds);

        if (!empty($testDb)) {
            exit(0);
        }
        // @todo offer to use the credentials we found to setup the test database.
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
        do {
            if ($retries === 3) {
                $this->say();
                $this->sayWarning('Something is not working, check the connections, credentials and try to run this ' .
                    'initialization script again.');
                exit(1);
            }

            if ($retries !== 0 && isset($dbCreds)) {
                $this->sayWarning(
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
                array_intersect_key($dbCreds, array_combine($mask, $mask))
                : $dbCreds;
        } while ($retries++ < 3
            && !($db = tryDbConnection(...buildDbCredsFromWpCreds($tryCreds))) instanceof \PDO
        );

        return $db;
    }

    protected function tryQuery($query, array $args = [], \PDO $db, array &$dbCreds)
    {
        $retries = 0;
        do {
            if ($retries === 3) {
                $this->saySomethingNotWorkingAndExit();
            }

            if (empty($args)) {
                $statement = $db->query($query);
            } else {
                $statement = $db->prepare($query)->execute($args);
            }
            if (!$statement instanceof \PDOStatement) {
                $this->sayWarning('Unable to run this query: ' . $query);
                $this->sayInfo('The specified database user might not have the required privileges;' .
                    ' please specify a user with all privileges on the database.');
                $db = $this->askForDbCreds($dbCreds, ['DB_HOST','DB_USER','DB_PASSWORD']);
            }
        } while ($retries++);

        return $statement;
    }

    protected function saySomethingNotWorkingAndExit()
    {
        $this->say();
        $this->sayWarning('Something is not working, check the connections, credentials and try to run this ' .
            'initialization script again.');
        $this->say();
        exit(1);
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
        $this->say('codeception.dist.yml created       <- global configuration');
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

    protected function sayHi()
    {
        $this->say();
        $this->sayTitle('wp-browser '.version().' setup');
        $this->say();
        $this->sayInfo('by Luca Tumedei <luca@theAverageDev.com>');
        $this->sayInfo('Docs: <bold>' . docs('/') . '</bold>');
    }/**
 * ${CARET}
 *
 * @param array $installationData
 * @since TBD
 *
 */
    protected function createSuites(array $installationData)
    {
        $this->createUnitSuite();
        $this->say("tests/unit created                 <- unit tests");
        $this->say("tests/unit.suite.yml written       <- unit tests suite configuration");
        $this->createWpUnitSuite(ucwords($installationData['wpunitSuite']), $installationData);
        $this->say("tests/{$installationData['wpunitSuiteSlug']} created               "
            . '<- WordPress unit and integration tests');
        $this->say("tests/{$installationData['wpunitSuiteSlug']}.suite.yml written     "
            . '<- WordPress unit and integration tests suite configuration');
        $this->createFunctionalSuite(ucwords($installationData['functionalSuite']), $installationData);
        $this->say("tests/{$installationData['functionalSuiteSlug']} created           "
            . "<- {$installationData['functionalSuiteSlug']} tests");
        $this->say("tests/{$installationData['functionalSuiteSlug']}.suite.yml written "
            . "<- {$installationData['functionalSuiteSlug']} tests suite configuration");
        $this->createAcceptanceSuite(ucwords($installationData['acceptanceSuite']), $installationData);
        $this->say("tests/{$installationData['acceptanceSuiteSlug']} created           "
            . "<- {$installationData['acceptanceSuiteSlug']} tests");
        $this->say("tests/{$installationData['acceptanceSuiteSlug']}.suite.yml written "
            . "<- {$installationData['acceptanceSuiteSlug']} tests suite configuration");
    }

    protected function sayError($message)
    {
        $this->say("<error>{$message}</error>");
    }

    protected function sayDone($interactive, array $installationData)
    {
        $this->say('---');
        $this->say();
        if ($interactive) {
            $this->saySuccess("Codeception is installed for {$installationData['acceptanceSuiteSlug']}, "
                . "{$installationData['functionalSuiteSlug']}, and WordPress unit testing");
        } else {
            $this->saySuccess("Codeception has created the files for the {$installationData['acceptanceSuiteSlug']}, "
                . "{$installationData['functionalSuiteSlug']}, WordPress unit and unit suites "
                . 'but the modules are not activated.');
        }
        $this->sayInfo('Some commands have been added in the Codeception configuration file: '
            . 'check them out using <comment>codecept --help</comment>');
        $this->say('---');
        $this->say();

        $this->say("<bold>Next steps:</bold>");
        $this->say('0. <bold>Create the databases used by the modules</bold>; wp-browser will not do it for you!');
        $this->say('1. <bold>Install and configure WordPress</bold> activating the theme and plugins you need to create'
            . ' a database dump in <comment>tests/_data/dump.sql</comment>');
        $this->say("2. Edit <bold>tests/{$installationData['acceptanceSuiteSlug']}.suite.yml</bold> to make sure WPDb "
            . 'and WPBrowser configurations match your local setup; change WPBrowser to WPWebDriver to '
            . 'enable browser testing');
        $this->say("3. Edit <bold>tests/{$installationData['functionalSuiteSlug']}.suite.yml</bold> to make sure "
            . 'WordPress and WPDb configurations match your local setup');
        $this->say("4. Edit <bold>tests/{$installationData['wpunitSuiteSlug']}.suite.yml</bold> to make sure WPLoader "
            . 'configuration matches your local setup');
        $this->say("5. Create your first {$installationData['acceptanceSuiteSlug']} tests using <comment>codecept "
            . "g:cest {$installationData['acceptanceSuiteSlug']} WPFirst</comment>");
        $this->say("6. Write a test in <bold>tests/{$installationData['acceptanceSuiteSlug']}/WPFirstCest.php</bold>");
        $this->say("7. Run tests using: <comment>codecept run {$installationData['acceptanceSuiteSlug']}</comment>");
        $this->say('---');
        $this->say();
        $this->sayInfo('Please note: <bold>due to WordPress extended use of globals and constants you should avoid ' .
            'running all the suites at the same time!</bold>');
        $this->sayInfo('Run each suite separately, like this: <comment>codecept run unit && codecept run '
            . "{$installationData['wpunitSuiteSlug']}</comment>, to avoid problems.");
    }/**
 * ${CARET}
 *
 * @return bool
 * @since TBD
 *
 */
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

    protected function tryCreateDb(\PDO $db, array &$dbCreds)
    {
        $testDbName = $this->projectName;
        $created = $this->tryQuery("CREATE DATABASE IF NOT EXISTS {$testDbName}", [], $db, $dbCreds);

        if (!$created instanceof \PDOStatement) {
            $this->saySomethingNotWorkingAndExit();
        }

        $this->sayInfo("Created the {$testDbName} database.");

        return $testDbName;
    }
}
