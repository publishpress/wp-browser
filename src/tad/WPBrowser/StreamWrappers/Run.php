<?php
/**
 * Represents the a stream wrapping operation meta data, collected from all the stream wrapper instances part of a
 * cohesive stream wrapping "run".
 *
 * @package tad\WPBrowser\StreamWrappers
 */

namespace tad\WPBrowser\StreamWrappers;

use SebastianBergmann\GlobalState\Snapshot;
use tad\WPBrowser\Http\Header;
use function tad\WPBrowser\pathNormalize;
use function tad\WPBrowser\pathResolve;

/**
 * Class Run
 *
 * @package tad\WPBrowser\StreamWrappers
 */
class Run
{
    const FILE_EXIT_DEFAULT = 'Stream_Wrapper_Run_File_Did_Not_Exit';
    /**
     * @var Header[] A collection of headers sent during the file inclusion, if any.
     */
    protected $sentHeaders = [];
    /**
     * A set of snapshots of the defined constants, globals and environment vars taken at diff. steps of the run.
     *
     * @var Snapshot[]
     */
    protected $snapshots;
    /**
     * An array of constants that will be defined before the file is loaded.
     *
     * @var array
     */
    protected $contextDefinedConstants = [];
    /**
     * An array of constants defined by the file, by means of a replaced `define` call.
     *
     * @var array
     */
    protected $definedConstants = [];
    /**
     * A list of files included by this stream wrapper.
     * @var array
     */
    protected $includedFiles = [];
    /**
     * The environment diff between before and after the file load.,
     *
     * @var array
     */
    protected $snapshotDiff;
    /**
     * The path to the last file loaded by the wrapper.
     *
     * @var string
     */
    protected $lastLoadedFile;

    /**
     * An array of constants replaced, in code, by the wrapper during a file inclusion.
     */
    protected $replacedConstants = [];

    /**
     * The output, buffered during the inclusion of the file.
     *
     * @var string
     */
    protected $output = '';
    /**
     * The message, or exit code, the file inclusion terminated with.
     *
     * @var string|int
     */
    protected $fileExit = self::FILE_EXIT_DEFAULT;
    /**
     * The list of files or directories this wrapper should wrap.
     *
     * @var array
     */
    protected $whitelist = [];

    /**
     * The last loaded file patched code.
     *
     * @var string
     */
    protected $lastLoadedFileCode = '';

    /**
     * Adds a file to those included during the stream wrapper run.
     *
     * @param string $file The included file path.
     * @param bool $once Whether this file was supposed to be included one (e.g. with `include_once` or `require_once).
     */
    public function addIncludedFile($file, $once = false)
    {
        $this->includedFiles[] = ['file' => $file, 'once' => $once];
    }

    /**
     * Returns whether a specific file was already included during the stream wrapper run or not.
     *
     * @param string $file The path to the file to check.
     * @return bool Whether a specific file was already included during the stream wrapper run or not.
     */
    public function fileWasIncluded($file)
    {
        return in_array($file, array_column($this->includedFiles, 'file'), true);
    }

    /**
     * Returns an array of "virtual" constants that will count as defined during the stream wrapper run.
     *
     * @return array An array of "virtual" constants that will count as defined during the stream wrapper run.
     */
    public function getContextDefinedConstants()
    {
        return $this->contextDefinedConstants;
    }

    /**
     * Sets an array of "virtual" constants that should be defined in a stream wrapper run.
     *
     * @param array $contextDefinedConstants An array of "virtual" constants that should be defined in a stream wrapper run.
     */
    public function setContextDefinedConstants(array $contextDefinedConstants)
    {
        $this->contextDefinedConstants = $contextDefinedConstants;
    }

    /**
     * Adds an header to those sent during this stream wrapper run.
     *
     * @param Header $header The header to add.
     * @param bool $replace Whether this header should replace a previous one or not.
     */
    public function addSentHeader(Header $header, $replace = false)
    {
        $prev = array_search($header->getName(), $this->sentHeaders, true);

        if ($prev && $replace) {
            $this->sentHeaders[$prev] = $header;
            return;
        }

        $this->sentHeaders[$header->getName()] = $header;
    }

    /**
     * Takes a named snapshot of the current real environment (constants, globals and vars).
     *
     * @param string $name The snapshot name.
     */
    public function snapshotEnv($name)
    {
        $this->snapshots[$name] = new Snapshot();
    }

    /**
     * Sets the last loaded file in the current stream wrapper run.
     *
     * @param string $file The path to the file.
     */
    public function setLastLoadedFile($file)
    {
        $this->lastLoadedFile = $file;
    }

    /**
     * Returns a list of constants whose `define` call was replaced.
     *
     * @param string $name The name of the constant whose `define` call was replaced.
     */
    public function addReplacedConstant($name)
    {
        $this->replacedConstants[] = $name;
    }

    /**
     * Returns the code or message the file exited with.
     *
     * @return int|string|false The code or message the file called `die` or `exit` with, or `false` if the file
     *                          did not `exit` or `die`.
     *
     * @see Run::fileDidExit() to discriminate between a `null` due to a file not exiting or just a `null` `die` or
     * `exit` parameter.
     */
    public function getExitCodeOrMessage()
    {
        return $this->fileDidExit() ? $this->fileExit : false;
    }

    /**
     * Checks whether the file inclusion terminated calling `exit` or `die` or not.
     *
     * @return bool Whether the file inclusion terminated calling `exit` or `die` or not.
     */
    public function fileDidExit()
    {
        return $this->fileExit !== self::FILE_EXIT_DEFAULT;
    }

    /**
     * Returns an associative list of headers sent by the file during load.
     *
     * @return array An associative list of headers sent by the file during load.
     */
    public function getSentHeaders()
    {
        return array_reduce($this->sentHeaders, static function ($headers, Header $header) {
            $headers[$header->getName()] = $header->getValue();
            return $headers;
        }, []);
    }

    /**
     * Returns a list of the constants that have been defined, or re-defined, during the file load.
     *
     * @return array A list of the constants that have been defined, or re-defined, during the file load.
     */
    public function getDefinedConstants()
    {
        return $this->definedConstants;
    }

    /**
     * Returns the HTTP response code sent either by an `HTTP` header, or by the last header that defined a response
     * code.
     *
     * @return int|string The last sent response code, or `200` if no response code was returned.
     */
    public function getSentResponsCode()
    {
        if (empty($this->sentHeaders)) {
            return null;
        }

        if (array_key_exists('HTTP', $this->sentHeaders)) {
            return $this->sentHeaders['HTTP']->getResponseCode();
        }

        $codes = array_map(static function (Header $header) {
            return $header->getResponseCode();
        }, $this->sentHeaders);

        return end($codes) ?: 200;
    }

    /**
     * Returns a list of names of constants whose definition was replaced in the stream wrapper run.
     *
     * @return array A list of names of constants whose definition was replaced in the stream wrapper run.
     */
    public function getReplacedConstants()
    {
        return $this->replacedConstants;
    }

    /**
     * Returns the output produced during the stream wrapper run.
     *
     * @return string The output produced during the stream wrapper run.
     */
    public function getOutput()
    {
        return $this->output;
    }

    /**
     * Sets the output produced during the stream wrapper run.
     *
     * @param string $output The output produced during the stream wrapper run.
     */
    public function setOutput($output)
    {
        $this->output = $output;
    }

    /**
     * Adds a "virtual" defined constant to this stream wrapper run.
     *
     * @param string $key The constant name.
     * @param mixed $value The constant value.
     */
    public function addDefinedConstant($key, $value)
    {
        $this->definedConstants[$key] = $value;
    }

    /**
     * Sets the file exit.
     *
     * @param string $fileExit The file exit.
     */
    public function setFileExit($fileExit)
    {
        $this->fileExit = $fileExit;
    }

    /**
     * Sets the list of directories or files this stream wrapper should wrap.
     *
     * @param array $whiteList The list of directories or files this stream wrapper should wrap.
     */
    public function setWhitelist(array $whiteList)
    {
        $this->whitelist = array_values(array_filter(array_map(static function ($path) {
            return pathResolve(pathNormalize($path));
        }, $whiteList)));
    }

    /**
     * Checks whether a path is whitelisted or not.
     *
     * @param string $path The path to check.
     * @return bool Whether a path is whitelisted or not.
     */
    public function isPathWhitelisted($path)
    {
        if (empty($this->whitelist)) {
            return true;
        }

        $path = pathResolve(pathNormalize($path));

        if (in_array($path, $this->whitelist, true)) {
            return true;
        }

        foreach ($this->whitelist as $whitelistedPath) {
            if (strpos($path, $whitelistedPath) === 0) {
                return true;
            }
        };

        return false;
    }

    /**
     * Returns the code of the last loded file.
     *
     * @return string The last loaded file patched code.
     */
    public function getLastLoadedFileCode()
    {
        return $this->lastLoadedFileCode;
    }

    /**
     * Sets the last loaded file patched code.
     *
     * @param string $lastLoadedFileCode The last loaded file patched code.
     */
    public function setLastLoadedFileCode($lastLoadedFileCode)
    {
        $this->lastLoadedFileCode = $lastLoadedFileCode;
    }

    /**
     * Returns the difference between the environment (globals, virtual constants and environment variables) before
     * and after the file is loaded.
     *
     * @return array The difference between before and after environment; an array of arrays.
     */
    protected function getDiff()
    {
        throw new \RuntimeException('Implement this: ' . __METHOD__);
        if ($this->snapshotDiff !== null) {
            return $this->snapshotDiff;
        }

        $this->snapshotDiff = [];

        foreach (static::$snapshotAfter as $group => list($key, $value)) {
            if (isset(static::$snapshotBefore[$group][$key])
                && static::$snapshotBefore[$group][$key] === static::$snapshotAfter[$group][$key]) {
                continue;
            }

            $this->snapshotDiff[$group][$key] = static::$snapshotAfter[$group][$key];
        }

        return $this->snapshotDiff;
    }
}
