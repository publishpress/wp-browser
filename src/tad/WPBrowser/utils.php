<?php
/**
 * Miscellaneous utility functions for the wp-browser library.
 *
 * @package tad\WPBrowser
 */

namespace tad\WPBrowser;

use Monad\FTry;
use Rah\Danpu\Dump;
use Rah\Danpu\Export;
use Rah\Danpu\Import;

/**
 * Builds an array format command line, compatible with the Symfony Process component, from a string command line.
 *
 * @param string|array $command The command line to parse, if in array format it will not be modified.
 *
 * @return array The parsed command line, in array format. Untouched if originally already an array.
 *
 * @uses \Symfony\Component\Process\Process To parse and escape the command line.
 */
function buildCommandline($command)
{
    if (empty($command) || is_array($command)) {
        return array_filter((array)$command);
    }

    $escapedCommandLine = (new \Symfony\Component\Process\Process($command))->getCommandLine();
    $commandLineFrags = explode(' ', $escapedCommandLine);

    if (count($commandLineFrags) === 1) {
        return $commandLineFrags;
    }

    $open = false;
    $unescapedQuotesPattern = '/(?<!\\\\)("|\')/u';

    return array_reduce($commandLineFrags, static function (array $acc, $v) use (&$open, $unescapedQuotesPattern) {
        $containsUnescapedQuotes = preg_match_all($unescapedQuotesPattern, $v);
        $v = $open ? array_pop($acc) . ' ' . $v : $v;
        $open = $containsUnescapedQuotes ?
            $containsUnescapedQuotes & 1 && (bool)$containsUnescapedQuotes !== $open
            : $open;
        $acc[] = preg_replace($unescapedQuotesPattern, '', $v);

        return $acc;
    }, []);
}

/**
 * Create the slug version of a string.
 *
 * This will also convert `camelCase` to `camel-case`.
 *
 * @param string $string The string to create a slug for.
 * @param string $sep The separator character to use, defaults to `-`.
 * @param bool $let Whether to let other common separators be or not.
 *
 * @return string The slug version of the string.
 */
function slug($string, $sep = '-', $let = false)
{
    $unquotedSeps = $let ? ['-', '_', $sep] : [$sep];
    $seps = implode('', array_map(static function ($s) {
        return preg_quote($s, '~');
    }, array_unique($unquotedSeps)));

    // Prepend the separator to the first uppercase letter and trim the string.
    $string = preg_replace('/(?<![A-Z' . $seps . '])([A-Z])/u', $sep . '$1', trim($string));

    // Replace non letter or digits with the separator.
    $string = preg_replace('~[^\pL\d' . $seps . ']+~u', $sep, $string);

    // Transliterate.
    $string = iconv('utf-8', 'us-ascii//TRANSLIT', $string);

    // Remove anything that is not a word or a number or the separator(s).
    $string = preg_replace('~[^' . $seps . '\w]+~', '', $string);

    // Trim excess separator chars.
    $string = trim(trim($string), $seps);

    // Remove duplicate separators and lowercase.
    $string = strtolower(preg_replace('~[' . $seps . ']{2,}~', $sep, $string));

    // Empty strings are fine here.
    return $string;
}

function renderString($template, array $data = [], array $fnArgs = [])
{
    $fnArgs = array_values($fnArgs);

    $replace = array_map(
        static function ($value) use ($fnArgs) {
            return is_callable($value) ? $value(...$fnArgs) : $value;
        },
        $data
    );

    if (false !== strpos($template, '{{#')) {
        /** @var \Closure $compiler */
        $compiler = \LightnCandy\LightnCandy::prepare(\LightnCandy\LightnCandy::compile($template));

        return $compiler($replace);
    }

    $search = array_map(
        static function ($k) {
            return '{{' . $k . '}}';
        },
        array_keys($data)
    );

    return str_replace($search, $replace, $template);
}

/**
 * Ensures a condition else throws an invalid argument exception.
 *
 * @param bool $condition The condition to assert.
 * @param string $message The exception message.
 */
function ensure($condition, $message)
{
    if ($condition) {
        return;
    }
    throw new \InvalidArgumentException($message);
}

/**
 * A safe wrapper around the `parse_url` function to ensure consistent return format.
 *
 * Differently from the internal implementation this one does not accept a component argument.
 *
 * @param string $url The input URL.
 *
 * @return array An array of parsed components, or an array of default values.
 */
function parseUrl($url)
{
    return \parse_url($url) ?: [
        'scheme' => '',
        'host' => '',
        'port' => 0,
        'user' => '',
        'pass' => '',
        'path' => '',
        'query' => '',
        'fragment' => ''
    ];
}

/**
 * Builds a \DateTimeImmutable object from another object, timestamp or `strtotime` parsable string.
 *
 * @param mixed $date A dates object, timestamp or `strtotime` parsable string.
 *
 * @return \DateTimeImmutable The built date or `now` date if the date is not parsable by the `strtotime` function.
 * @throws \Exception If the `$date` is a string not parsable by the `strtotime` function.
 */
function buildDate($date)
{
    if ($date instanceof \DateTimeImmutable) {
        return $date;
    }
    if ($date instanceof \DateTime) {
        return \DateTimeImmutable::createFromMutable($date);
    }

    return new \DateTimeImmutable(is_numeric($date) ? '@' . $date : $date);
}

/**
 * Finds a parent directory that passes a check.
 *
 * @param string $dir The path to the directory to check.
 * @param callable $check The check to run on the directory.
 *
 * @return bool|string The directory path, or `false` if not found.
 */
function findParentDirThat($dir, callable $check)
{
    do {
        if ($check($dir)) {
            return $dir;
        }

        $parent = dirname($dir);

        if ($dir === $parent) {
            return false;
        }

        $dir = $parent;
    } while ($dir);

    return false;
}

/**
 * Finds a directory, child to the current one, that passes a check.
 *
 * @param string $dir The path to the directory to check.
 * @param callable $check The check to run on the directory.
 *
 * @return bool|string The directory path, or `false` if not found.
 */
function findChildDirThat($dir, callable $check)
{
    $found = $check($dir);

    if ($found) {
        return $dir;
    }

    $dirs = new \CallbackFilterIterator(
        new \FilesystemIterator(
            $dir,
            \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::UNIX_PATHS | \FilesystemIterator::CURRENT_AS_PATHNAME
        ),
        static function ($f) {
            return is_dir($f);
        }
    );

    foreach ($dirs as $childDir) {
        if ($found = findChildDirThat($childDir, $check)) {
            return $found;
        }
    }

    return false;
}

/**
 * Normalizes a path to the Unix standard.
 *
 * @param string $path The path to normalize.
 *
 * @return string The normalized path.
 */
function pathNormalize($path)
{
    return implode('/', preg_split('#([/\\\])#u', $path) ?: []);
}

/**
 * Joins path fragments to form a unique, normalized, Unix path.
 *
 * @param mixed ...$frags The path fragments to join.
 *
 * @return string The joined, and Unix normalized, path fragments.
 */
function pathJoin(...$frags)
{
    return str_replace('\\', '/', implode(
        '/',
        array_reduce(
            $frags,
            static function (array $frags, $frag) {
                static $count;

                if ($count++ > 0) {
                    $frags[] = pathNormalize(trim($frag, '\\/'));
                } else {
                    $frags[] = pathNormalize(rtrim($frag, '\\/'));
                }

                return $frags;
            },
            []
        )
    ));
}

/**
 * Tries to open a connection to a database provided the coordinates.
 *
 * @param string $dsn The database dsn string.
 * @param string $user The db user.
 * @param string $passwd The db password.
 *
 * @return \PDO|false Either an open PDO connection, or `false` on failure.
 */
function tryDbConnection($dsn, $user, $passwd)
{
    try {
        return new \PDO($dsn, $user, $passwd);
    } catch (\Exception $e) {
        return false;
    }

    return false;
}

/**
 * Returns teh URL to a the documentation.
 *
 * @param string|null $path The relative path to the documentation section.
 *
 * @return string The full URL to the documentation.
 */
function docs($path = '/')
{
    return pathJoin('https://wpbrowser.wptestkit.dev/', $path);
}

/**
 * A utility function to just move on.
 */
function goOn()
{
    // no-op
}

/**
 * A function that will always return its input.
 *
 * @param mixed $input The function input.
 *
 * @return mixed The function input.
 */
function repeater($input)
{
    return $input;
}

/**
 * Returns the dir/file end of path.
 *
 * @param string $path The path to truncate.
 * @return string The last two components of a path.
 */
function pathTail($path, $length = 2)
{
    return implode('/', array_reverse(array_filter(
        array_map(static function () use (&$path) {
            $basename = basename($path);
            $path = dirname($path);
            return $basename;
        }, range(1, $length?:2))
    ))) ?: $path;
}

/**
 * Tries to dump a database to file.
 *
 * @param array $creds The database connection DSN, user and password.
 * @param string $file The path to the file.
 *
 * @return bool Whether the dump was successful or not.
 */
function dumpToFile(array $creds, $file)
{
    list($dsn, $user, $pass) = array_values($creds);

    $dump = new Dump();
    try {
        $dump->file($file)
            ->dsn($dsn)
            ->user($user)
            ->pass($pass)
            ->tmp(sys_get_temp_dir());

        new Export($dump);
    } catch (\Exception $e) {
        return false;
    }

    return true;
}

/**
 * Replaces a file backing it up.
 *
 * @param               string $file The path to the file to replace.
 * @param               string $data The data to write to file.
 * @param callable|null $left The function that will be called on failures, it will be passed a failure message.
 * @param callable|null $right The function that will be called on failures, it will be passed a flag indicating whether
 *                             the file was backed up or not.
 *
 * @return bool Whether the replacement was successful or not.
 */
function putFileReplacement($file, $data, callable $left = null, callable $right = null)
{
    $left = $left ?: 'tad\WPBrowser\goOn';
    $right = $right ?: 'tad\WPBrowser\goOn';

    $backedUp = false;
    if (file_exists($file)) {
        $backedUp = rename($file, $file. '.bak');

        if ($backedUp === false) {
            $left('unable to backup the file.', $backedUp);
            return false;
        }
    }

    if (file_exists($data)) {
        $put = rename($file, $data);
    } else {
        $put = file_put_contents($file, $data, LOCK_EX);
    }

    if ($put === false) {
        $left('unable to write or rename the file.', $backedUp);
        if ($backedUp) {
            renameFile($file. '.bak', $file, $left, $right);
        }
        return false;
    }

    $right($backedUp);

    return true;
}

/**
 * Imports a dump file to a database.
 *

 * @param array $creds The database connection DSN, user and password.
 * @param string $file The path to the file to import.
 *
 * @return bool Whether the import was successful or not.
 */
function importDump(array $creds, $file)
{
    list($dsn, $user, $pass) = array_values($creds);

    $dump = new Dump();
    try {
        $dump->file($file)
            ->dsn($dsn)
            ->user($user)
            ->pass($pass)
            ->tmp(sys_get_temp_dir());

        new Import($dump);
    } catch (\Exception $e) {
        return false;
    }

    return true;
}

/**
 * Renames a file.
 *
 * @param               string  $old The old file name.
 * @param               string  $new The new file name.
 * @param callable|null $left The function that will be called on failures, it will be passed a failure message.
 * @param callable|null $right The function that will be called on failures, it will be passed a flag indicating whether
 *                             the file was backed up or not.
 *
 * @return bool Whether the renaming was successful or not.
 */
function renameFile($old, $new, callable $left = null, callable $right = null)
{
    if (!file_exists($old)) {
        $left('old file does not exist.');
    }

    $left = $left ?: 'tad\WPBrowser\goOn';
    $right = $right ?: 'tad\WPBrowser\goOn';

    $renamed = rename($old, $new);

    if ($renamed === false) {
        $left('unable to move the file.');
        return false;
    }

    $right();

    return true;
}

/**
 * Converts a string to its camelCase version.
 *
 * @param string $string          The string to convert.
 * @param bool   $capitalizeFirst Whether to capitalize the first letter or not.
 *
 * @return string The camelCase version of the string.
 */
function camelCase($string, $capitalizeFirst = false)
{

    $str = str_replace('-', '', ucwords(slug($string, '-'), '-'));

    if (!$capitalizeFirst) {
        $str = lcfirst($str);
    }

    return $str;
}

/**
 * Try doing something a number of times.
 *
 * Uncatched exceptions are "silenced" in this method, if the need is to handle the exceptions in some way, then
 * the callable should handle the exceptions in some way.
 *
 * @param callable $f     The callable to call times.
 * @param int      $times The number of retries.
 *
 * @return mixed The function result.
 */
function tryTimes(callable $f, $times = 3 )
{
    $tries = 0;
    do {
        $isSuccess = FTry::create($f)->isSuccess();
        $tries++;
    } while (!$isSuccess || $tries < $times);

    return $isSuccess;
}

/**
 * Removes trailing slash from a path.
 *
 * @param string $path The path to remove the trailing slash from.
 *
 * @return string The clean path.
 */
function  pathUntrailslashit($path){
    return rtrim( $path, '/\\' );
}

/**
 * Returns the resolved and normalized path for a file.
 *
 * @param string $path The path to resolve.
 * @param string|null $root The root dir to resolve the path from.
 *
 * @return string The resolved path, or `false` on failure.
 */
function pathResolve($path, $root= null)
{
    if (empty($path)) {
        return false;
    }

    if (file_exists($path) && realpath($path) === $path) {
        return pathUntrailslashit(pathNormalize($path));
    }

    return $root ? pathUntrailslashit(pathNormalize(realpath(pathJoin($root, $path)))) : false;
}
