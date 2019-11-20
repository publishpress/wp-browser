<?php
/**
 * The base class for the Stream Wrappers.
 *
 * This class is heavily inspired by the antecedent/patchwork package.
 * @author  Ignas Rudaitis <ignas.rudaitis@gmail.com>
 * @license GPL-3.0+
 *
 * @package tad\WPBrowser\StreamWrappers
 */

namespace tad\WPBrowser\StreamWrappers;

/**
 * Class Stream
 *
 * @package tad\WPBrowser\StreamWrappers
 */
abstract class StreamWrapper
{
    const STREAM_OPEN_FOR_INCLUDE = 128;
    const STAT_MTIME_NUMERIC_OFFSET = 9;
    const STAT_MTIME_ASSOC_OFFSET = 'mtime';

    /**
     * The protocols this stream wrapper will handle.
     *
     * @var array
     */
    protected static $protocols = ['file', 'phar'];

    /**
     * The current stream context.
     *
     * @var resource
     */
    public $context;

    /**
     * The underlying resource for this stream.
     *
     * @var resource
     */
    public $resource;

    /**
     * Opens the stream, this method is called by any function opening a file to read or write.
     *
     * @param string $path       The path to the file to open.
     * @param string $mode       The mode used to open the file, see the `fopen` function.
     * @param int    $options    The stream open bit mask.
     * @param string $openedPath The full path to the effectively opened file, set by reference.
     *
     * @return bool Whether the stream opening was successful or not.
     */
    public function stream_open($path, $mode, $options, &$openedPath)
    {
        static::unwrap();
        $including = (bool)($options & self::STREAM_OPEN_FOR_INCLUDE);
        if ($including && $this->shouldTransform($path)) {
            $this->resource = $this->openAndTransform($path);
            self::wrap();

            return true;
        }
        if (isset($this->context)) {
            $this->resource = fopen($path, $mode, $options, $this->context);
        } else {
            $this->resource = fopen($path, $mode, $options);
        }
        static::wrap();

        return $this->resource !== false;
    }

    /**
     * Restores the default wrappers for the supported protocols and stops wrapping files.
     */
    public static function unwrap()
    {
        foreach (static::$protocols as $protocol) {
            set_error_handler(function () {
            });
            stream_wrapper_restore($protocol);
            restore_error_handler();
        }
    }

    /**
     * Checks whether this file should be manipulated or not.
     *
     * @param string $path The path to the file to manipulate.
     *
     * @return bool Whether this file should be manipulated or not.
     */
    abstract protected function shouldTransform($path);

    /**
     * Transforms the file and opens it.
     *
     * @param string $path The path to the file to open and transform.
     */
    protected function openAndTransform($path)
    {
        $resource = fopen('php://memory', 'rb+');
        $contents = file_get_contents($path, true);
        $contents = $this->patch($contents);

        fwrite($resource, $contents);
        rewind($resource);

        return $resource;
    }

    /**
     * Patches the contents of a file.
     *
     * @param string $contents The file contents to patch.
     *
     * @return string The patched file contents.
     */
    abstract protected function patch($contents);

    /**
     * Replaces the default stream wrapper for the supported protocols and starts wrapping.
     */
    public static function wrap()
    {
        foreach (static::$protocols as $protocol) {
            stream_wrapper_unregister($protocol);
            stream_wrapper_register($protocol, static::class);
        }
    }

    /**
     * Closes the stream.
     *
     * @return bool Whether the stream closing was successful or not.
     */
    public function stream_close()
    {
        return fclose($this->resource);
    }

    /**
     * Checks whether the stream is at the end or not.
     *
     * @return bool Whether the stream is at the end or not.
     */
    public function stream_eof()
    {
        return feof($this->resource);
    }

    /**
     * Flushes the stream output.
     *
     * @return bool Whether the data was successfully flushed or not.
     */
    public function stream_flush()
    {
        return fflush($this->resource);
    }

    /**
     * Read from the stream.
     *
     * @param int $count How many bytes of data should be read from the stream at the most.
     *
     * @return false|string The requested number of chars, or less if less are available, or `false` if no more data
     *                      is available.
     */
    public function stream_read($count)
    {
        return fread($this->resource, $count);
    }

    /**
     * Moves to a specific location in the stream.
     *
     * @param int $offset The offset to start seeking from.
     * @param int $whence One of the `SEEK_` constants values.
     *
     * @return bool Whether the move was successful or not.
     */
    public function stream_seek($offset, $whence = SEEK_SET)
    {
        return fseek($this->resource, $offset, $whence) === 0;
    }

    /**
     * Retrieves information about a file resource.
     *
     * @return array The resource information.
     */
    public function stream_stat()
    {
        $result = fstat($this->resource);
        if ($result) {
            $result[self::STAT_MTIME_ASSOC_OFFSET]++;
            $result[self::STAT_MTIME_NUMERIC_OFFSET]++;
        }

        return $result;
    }

    /**
     * Returns the current position in the stream.
     *
     * @return false|int The current position in the stream or `false` if the position is at the end of the file.
     */
    public function stream_tell()
    {
        return ftell($this->resource);
    }

    /**
     * Retrieves information about a file.
     *
     * @param string $path  The path to the file.
     * @param int    $flags A bit mask of options.
     *
     * @return array|false|null The stat elements or `false` on failure to stat.
     */
    public function url_stat($path, $flags)
    {
        static::unwrap();
        set_error_handler(function () {
        });
        try {
            $result = stat($path);
        } catch (\Exception $e) {
            $result = null;
        }
        restore_error_handler();
        static::wrap();
        if ($result) {
            $result[self::STAT_MTIME_ASSOC_OFFSET]++;
            $result[self::STAT_MTIME_NUMERIC_OFFSET]++;
        }

        return $result;
    }

    /**
     * Closes a directory handle.
     *
     * @return bool Whether the closing was successful or not.
     */
    public function dir_closedir()
    {
        closedir($this->resource);

        return true;
    }

    /**
     * Opens a directory handle.
     *
     * @param string $path    The path to the directory to open.
     * @param int    $options A bit mask of options.
     *
     * @return bool Whether the opening was successful or not.
     */
    public function dir_opendir($path, $options)
    {
        static::unwrap();
        if (isset($this->context)) {
            $this->resource = opendir($path, $this->context);
        } else {
            $this->resource = opendir($path);
        }
        static::wrap();

        return $this->resource !== false;
    }

    /**
     * Reads the next entry from a directory handle.
     *
     * @return false|string Either the next entry or `false` if there are no more entries.
     */
    public function dir_readdir()
    {
        return readdir($this->resource);
    }

    /**
     * Moves the directory handle pointer back to the first index.
     *
     * @return bool Whether the rewinding was successful or not.
     */
    public function dir_rewinddir()
    {
        rewinddir($this->resource);

        return true;
    }

    /**
     * Creates a new directory.
     *
     * @param string $path    The path to the directory to create.
     * @param int    $mode    The value passed to `mkdir`.
     * @param int    $options A bit mask of options.
     *
     * @return bool Whether the directory creation was succesful or not.
     */
    public function mkdir($path, $mode, $options)
    {
        static::unwrap();
        if (isset($this->context)) {
            $result = mkdir($path, $mode, $options, $this->context);
        } else {
            $result = mkdir($path, $mode, $options);
        }
        static::wrap();

        return $result;
    }

    /**
     * Renames a file or a directory.
     *
     * @param string $path_from The old file name.
     * @param string $path_to   The new file name.
     *
     * @return bool Whether the renaming was successful or not.
     */
    public function rename($path_from, $path_to)
    {
        static::unwrap();
        if (isset($this->context)) {
            $result = rename($path_from, $path_to, $this->context);
        } else {
            $result = rename($path_from, $path_to);
        }
        static::wrap();

        return $result;
    }

    /**
     * Removes a directory.
     *
     * @param string $path    The path to the directory to open.
     * @param int    $options A bit mask of options.
     *
     * @return bool Whether the directory was removed or not.
     */
    public function rmdir($path, $options)
    {
        static::unwrap();
        if (isset($this->context)) {
            $result = rmdir($path, $this->context);
        } else {
            $result = rmdir($path);
        }
        static::wrap();

        return $result;
    }

    /**
     * Retrieves the underlying resource from a stream.
     *
     * @param int $cast_as On of the `STREAM_CAST_` constant options.
     *
     * @return mixed|false The underlying resource used by the wrapper, or `false`.
     */
    public function stream_cast($cast_as)
    {
        return $this->resource;
    }

    /**
     * Advisory file locking.
     *
     * @param int $operation One of the `LOCK_` constants.
     *
     * @return bool Whether the file was locked or not.
     */
    public function stream_lock($operation)
    {
        if ($operation === '0' || $operation === 0) {
            $operation = LOCK_EX;
        }

        return flock($this->resource, $operation);
    }

    /**
     * Changes the stream options.
     *
     * @param int   $option One of the `STREAM_OPTION_` constants.
     * @param mixed $arg1   First argument for the option change.
     * @param mixed $arg2   Second argument for the option change.
     *
     * @return bool Whether the option update was successful or not.
     */
    public function stream_set_option($option, $arg1, $arg2)
    {
        switch ($option) {
            case STREAM_OPTION_BLOCKING:
                return stream_set_blocking($this->resource, $arg1);
            case STREAM_OPTION_READ_TIMEOUT:
                return stream_set_timeout($this->resource, $arg1, $arg2);
            case STREAM_OPTION_WRITE_BUFFER:
                return stream_set_write_buffer($this->resource, $arg1);
            case STREAM_OPTION_READ_BUFFER:
                return stream_set_read_buffer($this->resource, $arg1);
        }
    }

    /**
     * Writes to the file.
     *
     * @param mixed $data The data to write to file.
     *
     * @return false|int The amount of written bytes, or `false` if the writing failed.
     */
    public function stream_write($data)
    {
        return fwrite($this->resource, $data);
    }

    /**
     * Removes a file.
     *
     * @param string $path The path to the file to remove.
     *
     * @return bool Whether the file removal was successful or not.
     */
    public function unlink($path)
    {
        static::unwrap();
        if (isset($this->context)) {
            $result = unlink($path, $this->context);
        } else {
            $result = unlink($path);
        }
        static::wrap();

        return $result;
    }

    /**
     * Changes the stream metadata.
     *
     * @param string $path   The path or URL to set the metadata for.
     * @param int    $option One of the `STREAM_META_` options.
     * @param mixed  $value  The value for the option.
     *
     * @return bool Whether the metadata update was successful or not.
     */
    public function stream_metadata($path, $option, $value)
    {
        static::unwrap();
        switch ($option) {
            case STREAM_META_TOUCH:
                if (empty($value)) {
                    $result = touch($path);
                } else {
                    $result = touch($path, $value[0], $value[1]);
                }
                break;
            case STREAM_META_OWNER_NAME:
            case STREAM_META_OWNER:
                $result = chown($path, $value);
                break;
            case STREAM_META_GROUP_NAME:
            case STREAM_META_GROUP:
                $result = chgrp($path, $value);
                break;
            case STREAM_META_ACCESS:
                $result = chmod($path, $value);
                break;
        }
        static::wrap();

        return $result;
    }

    /**
     * Truncates the stream.
     *
     * @param int $new_size The new byte size for the stream.
     *
     * @return bool Whether the truncation was successful or not.
     */
    public function stream_truncate($new_size)
    {
        return ftruncate($this->resource, $new_size);
    }
}
