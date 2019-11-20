<?php
/**
 * Wraps a file to list the files that file included or required.
 *
 * @package tad\WPBrowser\StreamWrappers
 */

namespace tad\WPBrowser\StreamWrappers;

use function tad\WPBrowser\pathJoin;
use function tad\WPBrowser\pathNormalize;
use function tad\WPBrowser\pathResolve;

/**
 * Class IncludedFilesStreamWrapper
 *
 * @package tad\WPBrowser\StreamWrappers
 */
class IncludedFilesStreamWrapper extends StreamWrapper
{
    /**
     * Whether the wrapper type was already registered or not.
     *
     * @var bool
     */
    protected static $registered;

    /**
     * A list of files included by the file.
     *
     * @var array
     */
    protected static $includedFiles = [];

    /**
     * A string of code that will be inserted right after the file opening PHP tag.
     *
     * @var string|null
     */
    protected static $prefixCode;

    /**
     * The file that is being included for inspection.
     *
     * @var string
     */
    protected static $targetFile;

    /**
     * Returns a list of the files included by this file.
     *
     * Files are not included and any file that, due to flow, would not be included will not be included in the list.
     *
     * @param string      $file       The file to include.
     * @param string|null $prefixCode The code to prefix to the file contents, right after the opening PHP tag.
     *
     * @return array An array of files included by this file. There is no guarantee file exist.
     *
     * @throws StreamWrapperException If the file cannot be found.
     */
    public function getIncludedFiles($file, $prefixCode = null)
    {
        static::$includedFiles = [];
        static::$prefixCode    = $prefixCode;

        if ( ! file_exists($file)) {
            throw new StreamWrapperException('File "' . $file . '" does not exist.');
        }

        static::$targetFile = pathResolve($file);

        static::wrap();
        include $file;
        static::unwrap();

        return static::$includedFiles;
    }

    /**
     * Captures the included or required file path.
     *
     * @param string $path The included or required file path. The file might not exist.
     * @param string $dir  The directory of the file that is including this one, useful for relative paths.
     */
    public function includeFile($path, $dir)
    {
        if (empty($path)) {
            return;
        }

        $filename = pathNormalize(pathJoin($dir, $path));
        if (file_exists($filename)) {
            $path = $filename;
        }

        static::$includedFiles[] = $path;
    }

    /**
     * Replaces occurrences of the (include|require)(_once)* instructions with calls to the value collecting function.
     *
     * @param string $contents The contents to patch.
     */
    protected function patch($contents)
    {
        if (static::$prefixCode !== null) {
            $contents = preg_replace(
                '/(^<\\?php)/um',
                sprintf('$1%s', PHP_EOL . static::$prefixCode . PHP_EOL),
                $contents
            );
        }

        $GLOBALS['wpb_includes'] = $this;
        $str                     = '/^\\s*(?:include|require)(?:_once)*\\s*([^;]+)/um';
        $replacement             = '\\$GLOBALS["wpb_includes"]->includeFile($1, __DIR__)';

        return preg_replace($str, $replacement, $contents);
    }

    /**
     * Whether a path should be transformed or not.
     *
     * @return bool Whether the path to transform is the current file or not.
     */
    protected function shouldTransform($path)
    {
        return pathResolve($path) === static::$targetFile;
    }
}
