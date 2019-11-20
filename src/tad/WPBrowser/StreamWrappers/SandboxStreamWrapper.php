<?php
/**
 * Loads a single file, and all the files required or included by it, in a "sandbox" environment that allows rolling
 * back side-effects.
 *
 * @package tad\WPBrowser\StreamWrappers
 */

namespace tad\WPBrowser\StreamWrappers;

use tad\WPBrowser\Http\Header;
use function tad\WPBrowser\pathJoin;
use function tad\WPBrowser\pathNormalize;

/**
 * Class SandboxStreamWrapper
 *
 * @package tad\WPBrowser\StreamWrappers
 */
class SandboxStreamWrapper extends StreamWrapper
{
    const T_ANYTHING = 'T_ANYTHING';
    /**
     * The wrapper "run" meta data collector.
     *
     * @var Run
     */
    protected static $run;

    /**
     * SandboxStreamWrapper constructor.
     */
    public function __construct()
    {
        if (static::$run === null) {
            static::$run = new Run();
        }
    }

    /**
     * @inheritDoc
     */
    protected static function scheme()
    {
        return 'file';
    }

    /*
     * Unregisters the stream wrapper and restores the default one.
     */

    /**
     * Loads a file, and with it any file that might be consequentially loaded, in a "sandbox".
     *
     * @param string $file The path to the file to load.
     *
     * @return Run The stream wrapper run result.
     *
     * @throws StreamWrapperException If the file does not exist or there's an issue registering the wrapper.
     */
    public function loadFile($file)
    {
        if (!file_exists($file)) {
            throw new StreamWrapperException(sprintf('File "%s" does not exist.', $file));
        }

        if (!empty(static::$run->getContextDefinedConstants())) {
            foreach (static::$run->getContextDefinedConstants() as $key => $value) {
                $this->define($key, $value, false);
            }
        }

        static::$run->snapshotEnv('before');

        $GLOBALS['wpb_sandbox'] = $this;

        ob_start();

        static::wrap();
        static::$run->setLastLoadedFile($file);

        try {
            include $file;
        } catch (ExitSignal $e) {
            static::$run->setFileExit($e->getMessage());
        } catch (\Exception $e){
            $message = $e->getMessage();
            if(preg_match('/^Use of undefined constant (\\w*)/',$message,$m)){
                throw new StreamWrapperException(
                    sprintf(
                        'Constant "%s" is undefined: define it with the "setContextDefinedConstants" method.',
                        $m[1]
                    )
                );
            }

            $trace = $e->getTrace();
            $last = reset($trace);
            $line = $last['line'];
            $patchedCodeLines = explode(PHP_EOL, static::$run->getLastLoadedFileCode());
            $relevantLines = array_splice(
                $patchedCodeLines,
                $line - 10, 20
            );
            throw new StreamWrapperException(
                sprintf(
                    "%s; patched code:\n%s\n\n%s",
                    $e->getMessage(),
                    $line,
                    implode(PHP_EOL, $relevantLines)
                )
            );
        }

        static::unwrap();

        static::$run->setOutput(ob_get_clean());

        static::$run->snapshotEnv('after');

        $thisRun = static::$run;

        static::$run = null;

        return $thisRun;
    }

    /**
     * A replacement for the `define` functions that will set environment vars instead and log the constant definition.
     *
     * @param string $key The defined constant key.
     * @param mixed $value The defined constant value.
     * @param bool $track Whether the definition of this virtual constant should be tracked or not.
     *
     * @return bool Whether the "virtual" constant was correctly defined or not.
     */
    public function define($key, $value, $track = true)
    {
        if ($track) {
            static::$run->addDefinedConstant($key, $value);
        }

        return putenv("{$key}={$value}");
    }

    /**
     * Replaces calls to the `defined` function to check if a "virtual" constant was defined either in an included
     * file or before it by means of the `setContextDefinedConstants` method.
     *
     * @param string $const The constant to check for definition.
     *
     * @return bool Whether the "virtual" constant is defined or not.
     */
    public function defined($const)
    {
        return array_key_exists($const, static::$run->getContextDefinedConstants())
            || array_key_exists($const, static::$run->getDefinedConstants());
    }

    /**
     * Sets an associative array of virtual constants that will be defined before the file is loaded.
     *
     * @param array $definedConstants An associative array of virtual constants that will be defined before the file is
     *                                loaded.
     */
    public function setContextDefinedConstants(array $definedConstants)
    {
        static::$run->setContextDefinedConstants($definedConstants);
    }

    /**
     * Includes a file, replacement for the `includeOnce` function.
     *
     * @param string $file The path to the file to include.
     * @param string $cwd The current working directory, to resolve relative paths.
     *
     * @return mixed The included file return value, if any, or `true` if the file was already included..
     *
     * @throws StreamWrapperException If the file to include does not exist.
     */
    public function includeFileOnce($file, $cwd, array $definedVars = [])
    {
        return $this->includeFile($file, $cwd, $definedVars, true);
    }

    /**
     * Includes a file, replacement for the `include` function.
     *
     * @param string $file The path to the file to include.
     * @param string $cwd The current working directory, to resolve relative paths.
     * @param array $definedVars The variables defined the moment this file is included.
     * @param bool $once Whether to include this file once or not.
     *
     * @return mixed The included file return value, if any, or `true` if the file was already included..
     *
     * @throws StreamWrapperException If the file to include does not exist.
     */
    public function includeFile($file, $cwd, array $definedVars = [], $once = false)
    {
        $filename = pathNormalize(pathJoin($cwd, $file));

        if (file_exists($filename)) {
            $file = $filename;
        }

        if (!file_exists($file)) {
            throw new StreamWrapperException('Including file "' . $file . '" but it does not exist.');
        }

        if ($once && static::$run->fileWasIncluded($file)) {
            return true;
        }

        static::$run->addIncludedFile($file, $once);

        unset($definedVars['file']);

        /** @noinspection NonSecureExtractUsageInspection Normal include var pass-thru will not work. */
        extract($definedVars);

        return include $file;
    }

    /**
     * Returns the value of a "virtual" constant.
     *
     * @param string $const The constant name.
     *
     * @return mixed|null The value of the "virtual" constant, or `null` if not defined.
     */
    public function getConst($const)
    {
        $controlledConstants = array_merge(
            static::$run->getContextDefinedConstants(),
            static::$run->getDefinedConstants()
        );

        if (isset($controlledConstants[$const])) {
            return $controlledConstants[$const];
        }

        return null;
    }

    /**
     * Throws an `ExitSignal` that will be catched in the `load` method.
     *
     * @param int|string $status The `exit` or `die` status or code.
     *
     * @throws ExitSignal To signal the loaded code is willing to interrupt the flow and stop.
     */
    public function throwExit($status)
    {
        throw new ExitSignal($status);
    }

    /**
     * Drop-in replacement for the `header` function.
     *
     * @param string $value The header value.
     * @param bool $replace Whether this header replaces a previous version of it or not.
     * @param int|null $httpResponseCode The header response code; ignored if the `$value` is empty.
     */
    public function header($value, $replace = true, $httpResponseCode = null)
    {
        $header = Header::make($value, $replace, $httpResponseCode, static::$run->getSentResponsCode());
        static::$run->addSentHeader($header, $replace);
    }

    /**
     * Returns the stream wrapper las run result, if any.
     *
     * @return Run
     */
    public function getRunResult()
    {
        return static::$run;
    }

    /**
     * Sets the list of directories or files this stream wrapper should wrap.
     *
     * @param array $whiteList The list of directories or files this stream wrapper should wrap.
     */
    public function setWhitelist(array $whiteList)
    {
        static::$run->setWhiteList($whiteList);
    }

    /**
     * @inheritDoc
     */
    protected function patch($contents)
    {
        $this->replaceDefineCalls($contents);
        $this->replaceDefinedCalls($contents);
        $this->replaceConstAccessCalls($contents);
        $this->replaceIncludeCalls($contents);
        $this->replaceExitCalls($contents);
        $this->replaceHeadersFunctions($contents);

        static::$run->setLastLoadedFileCode($contents);

        return $contents;
    }

    /**
     * Replaces calls to the `define` function with calls to the `wpb_define` method of this class..
     *
     * @param string $contents The contents to replace; passed by reference.
     */
    protected function replaceDefineCalls(&$contents)
    {
        $contents = $this->replaceFunctionCallWith('define', static function ($function, $buffer) {
            preg_match('/^define\\s*\\(\\s*[\'"](?<const>\\w+)/um', $buffer, $m);
            $constName = $m['const'];
            static::$run->addReplacedConstant($constName);
            return preg_replace(
                '/^define(.*)$/',
                '$GLOBALS["wpb_sandbox"]->define$1',
                $buffer
            );
        }, $contents);
    }

    protected function replaceFunctionCallWith($fn, callable $patchFn, $contents)
    {
        $tokens = token_get_all($contents);

        $patched = '';
        $capturingLevel = 0;
        $buffer = '';

        foreach ($tokens as $token) {
            if ($capturingLevel && $token === ')' && --$capturingLevel === 1) {
                $patched .= $patchFn($fn, $buffer . ')');
                $buffer = '';
                $capturingLevel = 0;
                continue;
            }

            $tokenType = is_array($token) ? $token[0] : $token;
            $tokenValue = is_array($token) ? $token[1] : $token;

            if ($tokenType === T_STRING && $tokenValue === $fn) {
                $capturingLevel = 1;
            } elseif ($capturingLevel && $tokenValue === '(') {
                $capturingLevel++;
            }

            if ($capturingLevel) {
                $buffer .= $tokenValue;
            } else {
                $patched .= $tokenValue;
            }
        }

        return $patched;
    }

    /**
     * Replaces calls to the `defined` function with calls to the `defined` method of this class.
     *
     * @param string $contents The contents to modify, passed by reference.
     */
    protected function replaceDefinedCalls(&$contents)
    {
        $contents = $this->replaceFunctionCallWith('defined', static function ($function, $buffer) {
            return preg_replace(
                '/^defined(.*)$/',
                '$GLOBALS["wpb_sandbox"]->defined$1',
                $buffer
            );
        }, $contents);
    }

    /**
     * Replaces constant value access, via string or `constant` function, with a redirection to the wrapper constant
     * pool.
     *
     * @param string $contents The contents to modify, passed by reference.
     */
    protected function replaceConstAccessCalls(&$contents)
    {
        $controlledConstants = array_merge(
            array_keys(static::$run->getContextDefinedConstants()),
            array_keys(static::$run->getDefinedConstants()),
            static::$run->getReplacedConstants()
        );

        $contents = $this->replaceTokensWith($controlledConstants, static::T_ANYTHING,
            static function ($const) {
                return '$GLOBALS["wpb_sandbox"]->getConst("' . $const[1] . '")';
            }, $contents, false);

        $contents = $this->replaceFunctionCallWith('constant', static function ($function, $buffer) {
            return preg_replace(
                '/^constant(.*)$/',
                '$GLOBALS["wpb_sandbox"]->getConst$1',
                $buffer
            );
        }, $contents);
    }

    protected function replaceTokensWith(
        array $targetTokens,
        $endOfCapture,
        callable $patchFn,
        $contents,
        $matchType = true
    ) {
        $tokens = token_get_all($contents);

        $patched = '';
        $capturing = false;
        $buffer = '';

        foreach ($tokens as $token) {
            $tokenType = is_array($token) ? $token[0] : $token;
            $tokenValue = is_array($token) ? $token[1] : $token;

            if ($capturing && ($endOfCapture === static::T_ANYTHING || $token === $endOfCapture)) {
                $patched .= $patchFn($capturing, $buffer) . $tokenValue;
                $buffer = '';
                $capturing = false;
                continue;
            }

            $compare = $matchType ? $tokenType : $tokenValue;

            $capturing = in_array($compare, $targetTokens, true) ? $token : $capturing;

            if ($capturing) {
                $buffer .= $tokenValue;
            } else {
                $patched .= $tokenValue;
            }
        }

        return $patched;
    }

    /**
     * Changes calls to `include` and `require` to calls to the controlled inclusion methods.
     *
     * @param string $contents The contents to modify, passed by reference.
     */
    protected function replaceIncludeCalls(&$contents)
    {
        $contents = $this->replaceTokensWith([T_REQUIRE, T_REQUIRE_ONCE, T_INCLUDE, T_INCLUDE_ONCE], ';',
            static function ($token, $buffer) {
                if ($token[0] === T_REQUIRE_ONCE || $token[0] === T_INCLUDE_ONCE) {
                    return preg_replace(
                        '/^' . $token[1] . '(.*)$/',
                        '$GLOBALS["wpb_sandbox"]->includeFileOnce($1, __DIR__, get_defined_vars());',
                        $buffer
                    );
                }
                return preg_replace(
                    '/^' . $token[1] . '(.*)$/',
                    '$GLOBALS["wpb_sandbox"]->includeFile($1, __DIR__, get_defined_vars());',
                    $buffer
                );
            }, $contents);
    }

    /**
     * Replaces calls to `die` or `exit` with calls to the `throwExit` method of this class.
     *
     * @param string $contents The contents to patch, passed by reference.
     */
    protected function replaceExitCalls(&$contents)
    {
        $contents = preg_replace(
            '/(?<![\\w\'"])(?:die|exit)(?![\\w\'"])\\s*\\(/um',
            '\$GLOBALS["wpb_sandbox"]->throwExit(',
            $contents
        );
    }

    /**
     * Replaces calls to the `header` function with calls to the `header` method of this class.
     *
     * @param string $contents The contents to modify, passed by reference.
     */
    protected function replaceHeadersFunctions(&$contents)
    {
        $contents = preg_replace(
            '/(?<![\\w])header(?![\\w\\s])/ux',
            '$GLOBALS["wpb_sandbox"]->header',
            $contents
        );
    }

    /**
     * @inheritDoc
     */
    protected function shouldTransform($path)
    {
        return static::$run->isPathWhitelisted($path);
    }
}
