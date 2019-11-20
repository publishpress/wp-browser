<?php
/**
 * Functions dedicated to WordPress interaction and inspection.
 *
 * @package tad\WPBrowser;
 */

namespace tad\WPBrowser;

/**
 * Finds the WordPress root directory in a parent or child directory.
 *
 * @param string|null $startDir The path to the directory to check, or `null` to use the current working directory.
 * @param string|null $default The default value to return, or `null` to use the current working directory.
 *
 * @return string The path to the WordPress root folder, if found, or the default value.
 */
function findWordPressRootDir($startDir = null, $default = null)
{
    $getcwd = getcwd();

    if ($getcwd === false) {
        return $default;
    }

    $startDir = $startDir ?: (string)$getcwd;
    $default = $default ?: (string)$getcwd;

    $dir = $startDir;

    $isWpRoot = static function ($dir) {
        return file_exists($dir . '/wp-load.php');
    };

    $match = findParentDirThat($dir, $isWpRoot);

    if (!$match) {
        $match = findChildDirThat($dir, $isWpRoot);
    }

    return $match ?: $default;
}

/**
 * Finds the plugin main file in a directory or in a file parent directory.
 *
 * @param string $dirOrFile The directory, or the child file, that should be searched for the plugin main file.
 * @return string|false Either the absolute path to the plugin main file, or `false` if not found.
 */
function findPluginFile($dirOrFile)
{
    if (is_file($dirOrFile)) {
        $dirOrFile = dirname($dirOrFile);
    }

    $flags = \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::CURRENT_AS_PATHNAME;
    $candidates = new \CallbackFilterIterator(
        new \FilesystemIterator($dirOrFile, $flags),
        static function ($file) {
            return !is_dir($file);
        }
    );

    foreach ($candidates as $candidate) {
        $f = fopen($candidate, 'rb');

        if ($f === false) {
            continue;
        }

        $start = fread($f, 8192);
        fclose($f);

        if ($start === false) {
            continue;
        }

        $start = str_replace("\r", "\n", $start);

        if (! preg_match('/^[ \t\/*#@]*Plugin Name:(.*)$/mi', $start)) {
            continue;
        }

        return realpath($candidate);
    }

    return false;
}

/**
 * Returns the path to the `wp-config.php` file for a WordPress installation root directory.
 *
 * @param string $wpRootDir The path to the WordPress root directory.
 *
 * @return string|false the path to the `wp-config.php` file for a WordPress installation root directory else `false`.
 */
function findWpConfigFile($wpRootDir)
{
    if (empty($wpRootDir)) {
        return false;
    }

    $wpConfigFile = pathJoin($wpRootDir, 'wp-config.php');

    if (file_exists($wpConfigFile)) {
        return $wpConfigFile;
    }

    $wpConfigFile = dirname($wpRootDir) . '/wp-config.php';
    $wpSettingsFile = dirname($wpRootDir) . '/wp-settings.php';

    if (!file_exists($wpSettingsFile) && file_exists($wpConfigFile)) {
        return $wpConfigFile;
    }

    return false;
}

/**
 * Returns an array of the variables and constants defined in the `wp-config.php` file.
 *
 * @param string $wpRootDir The path to the WordPress root directory.
 *
 * @return array An array of the variables and constants defined in the WordPress `wp-config.php` file; the array
 *                     has shape `['vars' => <...vars>, 'constants' => <...constants>]`, an empty array on failure.
 */
function getWpConfigArgs($wpRootDir)
{
    $wpConfigFile = findWpConfigFile($wpRootDir);

    if (!($wpConfigFile && is_readable($wpConfigFile))) {
        $cache[$wpRootDir]= [];
        return [];
    }

    $wpConfigCode = file_get_contents($wpConfigFile);

    if ($wpConfigCode === false) {
        return [];
    }

    // Remove the leading `<?php` tag.
    $wpConfigCode = preg_replace('/^<\\?php.*$/um', '', $wpConfigCode);
    // Do not load the `wp-settings.php` file.
    $wpConfigCode = preg_replace('/^.*wp-settings\\.php.*$/um', '', $wpConfigCode);

    /*
     * To "correctly", as much as possible, pick up the values, set the context of the request to a test one.
     */
    $code = [
        '<?php',
        "putenv('WPBROWSER_HOST_REQUEST=1');",
        "putenv('TEST_REQUEST=1');",
        '$_SERVER["HTTP_X_TEST_REQUEST"] = true;',
        '$_SERVER["HTTP_X_WPBROWSER_REQUEST"] = true;',
        '$_constantsBefore = get_defined_constants();',
        '$_varsBefore = get_defined_vars();',
        $wpConfigCode,
        '$_constants = array_diff_key((array)get_defined_constants(), (array)$_constantsBefore);',
        '$_vars = array_diff_key((array)get_defined_vars(), (array)$_varsBefore);',
        'unset($_vars["_constantsBefore"],$_vars["_varsBefore"], $_vars["_constants"], $_vars["_vars"]);',
        'exit(json_encode(["constants" => $_constants,"vars" => $_vars], JSON_PRETTY_PRINT));'
    ] ;

    $phpProcess = new \Symfony\Component\Process\PhpProcess(
        implode(PHP_EOL, $code),
        dirname($wpConfigFile)
    );
    $phpProcess->run();
    $output = $phpProcess->getOutput();

    if ($phpProcess->getExitCode() !== 0 || empty($output)) {
        return [];
    }

    $decoded = json_decode($output, true);

    $vars =  false === $decoded ? [] : $decoded;

    return $vars;
}

/**
 * Parses the database connection credentials from the WordPress configuration file, `wp-config.php`.
 *
 * @param string $wpRootDir The path to the WordPress root directory, the one containing the `wp-load.php` file.
 *
 * @return array The database connection credentials ('DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASSWORD') or an empty array.
 */
function findWpDbCreds($wpRootDir)
{
    try {
        $wpConfigArgs = getWpConfigArgs($wpRootDir);
    } catch (\ReflectionException $e) {
        return [];
    }

    $expectedDbCredsConstants = ['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASSWORD'];
    $dbCreds                  = [];
    foreach ($expectedDbCredsConstants as $const) {
        if (! isset($wpConfigArgs['constants'][$const])) {
            continue;
        }

        $dbCreds[$const] = $wpConfigArgs['constants'][$const];
    }

    if (isset($wpConfigArgs['vars']['table_prefix'])) {
        $dbCreds['table_prefix'] = $wpConfigArgs['vars']['table_prefix'];
    }

    return count($dbCreds) === count($expectedDbCredsConstants) + 1 ? $dbCreds : [];
}

/**
 * Builds database connection credentials from credentials in the constant-based format used by WP.
 *
 * @param array $dbCreds The WP db connection credentials.
 *
 * @return array The db credentials in the shape [$dsn, $user, $password].
 */
function buildDbCredsFromWpCreds(array $dbCreds)
{
    $dbName = isset($dbCreds['DB_NAME']) ? $dbCreds['DB_NAME'] : false;
    $dbHost = isset($dbCreds['DB_HOST']) ? $dbCreds['DB_HOST'] : 'localhost';
    list($dbHost, $dbPort) = strpos($dbHost, ':') > 1
        ? explode(':', $dbHost)
        : [$dbHost, '3306'];
    $dbUser = isset($dbCreds['DB_USER']) ? $dbCreds['DB_USER'] : 'root';
    $dbPass = isset($dbCreds['DB_PASSWORD']) ? $dbCreds['DB_PASSWORD'] : '';

    if ($dbName) {
        $dsn = sprintf('mysql:dbname=%s;host=%s;port=%d', $dbName, $dbHost, $dbPort);
    } else {
        $dsn = sprintf('mysql:host=%s;port=%d', $dbHost, $dbPort);
    }

    return [$dsn, $dbUser, $dbPass];
}

/**
 * Finds all the plugin files in the WordPress plugins directory.
 *
 * @param string $wpRootDir The path to the WordPress installation root directory.
 * @return array An array of the absolute paths to each found plugin file.
 */
function findPluginFiles($wpRootDir)
{
    $pluginFiles = [];

    $wpConfigArgs = getWpConfigArgs($wpRootDir);
    $contentDir = isset($wpConfigArgs['constants']['WP_CONTENT_DIR']) ?
        $wpConfigArgs['constants']['WP_CONTENT_DIR']
        : pathJoin($wpRootDir, 'wp-content');
    $pluginsDir = isset($wpConfigArgs['constants']['WP_PLUGIN_DIR']) ?
        $wpConfigArgs['constants']['WP_PLUGIN_DIR']
        : pathJoin($contentDir, 'plugins');

    $pluginDirs = new \FilesystemIterator(
        $pluginsDir,
        \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::CURRENT_AS_PATHNAME
    );

    foreach ($pluginDirs as $pluginDir) {
        $pluginFiles[] = findPluginFile($pluginDir);
    }

    return array_filter($pluginFiles);
}

/**
 * Returns an array of all the theme directories found in the directory.
 *
 * @param string $wpRootDir The path to the WordPress root directory.
 * @return array A list of theme directories found in the WordPress root directory.
 */
function findThemesInDir($wpRootDir)
{
    $themes = [];

    $themesDir =  pathJoin(getWpContentDir($wpRootDir), 'themes');

    $themeDirs = new \FilesystemIterator(
        $themesDir,
        \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::CURRENT_AS_PATHNAME
    );

    foreach ($themeDirs as $themeDir) {
        if (!file_exists($themeDir . '/style.css')) {
            continue;
        }
        $themes[] = $themeDir;
    }

    return array_filter($themes);
}

/**
 * Returns the path to the WordPress content directory given a WordPress root folder.
 *
 * @param string $wpRootDir The path to the WordPress root directory.
 *
 * @return string The path to the WordPress content directory.
 */
function getWpContentDir($wpRootDir)
{
    $wpConfigArgs = getWpConfigArgs($wpRootDir);
    return isset($wpConfigArgs['constants']['WP_CONTENT_DIR']) ?
        $wpConfigArgs['constants']['WP_CONTENT_DIR']
        : pathJoin($wpRootDir, 'wp-content');
}

/**
 * Returns the full code of the wp-db.php drop-in that will use `WP_DB_` environment variables to crate the `$wpdb`
 * object.
 *
 * @return string The drop-in code.
 */
function dbDropInForEnv()
{
    return implode(
        PHP_EOL,
        [
            '<?php',
            'global $wpdb;',
            '$dbUser = getenv( "WP_DB_USER" ) ?: false;',
            '$dbPassword = getenv( "WP_DB_PASSWORD" ) ?: false;',
            '$dbName = getenv( "WP_DB_NAME" ) ?: false;',
            '$dbHost = getenv( "WP_DB_HOST" ) ?: false;',
            'if( false !== $dbUser && false !== $dbPassword && false !== $dbName && false !== $dbHost ) {',
            '   $wpdb = new wpdb( $dbUser, $dbPassword, $dbName, $dbHost );',
            '}'
        ]
    );
}

/**
 * Returns the value of a constant defined in the wp-config.php file, if defined.
 *
 * @param string $wpRootDir The path to the WordPress root directory.
 * @param string $const     The name of the constant to return.
 * @return mixed|null The constant value, if set, or `null`.
 */
function getWpConfigConstant($wpRootDir, $const)
{
    return isset(getWpConfigArgs($wpRootDir)['constants'][$const]) ?
        getWpConfigArgs($wpRootDir)['constants'][$const]
        : null;
}

/**
 * Returns the absolute path to a theme style.css file, given the theme root directory.
 *
 * @param string $rootDir The theme root directory path.
 * @return string|false Either the absolute path to the theme style.css file, or `false` if not found.
 */
function findThemeStyleFile($rootDir)
{
    $styleFile = realpath(pathJoin($rootDir, 'style.css'));
    return $styleFile && file_exists($styleFile) ? $styleFile : false;
}
