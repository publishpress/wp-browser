<?php
/**
 * Provides methods to get information about a test case and methods.
 *
 * @package tad\WPBrowser\Traits
 */

namespace tad\WPBrowser\Traits;

use Codeception\Util\ReflectionHelper;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Trait WithTestnames
 *
 * @package tad\WPBrowser\Traits
 */
trait WithTestnames
{
    /**
     * Keeps a counter for each class, function and data-set combination.
     *
     * @var array
     */
    private static $counters = [];

    /**
     * A list of method names provided by the SnapshotAssertions trait.
     *
     * @var array
     */
    private static $traitMethods = [];

    /**
     * Returns the name of the tests case, method and data set.
     *
     * @return string The current test case and method name.
     * @throws \ReflectionException If there's an issue reflecting on the current object.
     */
    protected function getTestName()
    {
        list($class, $function, $object) = $this->getTestClassFunctionObject();

        $classFrags = explode('\\', $class);
        $classBasename = array_pop($classFrags);

        $dataSetFrag = '';
        if ($object instanceof TestCase) {
            /** @var TestCase $testCase */
            $testCase = $object;
            $dataName = $this->getDataName($testCase);
            if ($dataName !== '') {
                $dataSetFrag = '__' . $dataName;
            }
        }
        $name = sprintf(
            '%s__%s%s__%d',
            $classBasename,
            $function,
            $dataSetFrag,
            $this->getCounterFor($class, $function, $dataSetFrag)
        );

        return $name;
    }

    /**
     * Returns this trait method names, used to filter them out of the backtrace.
     *
     * @return array An array of this trait methods.
     * @throws \ReflectionException If there's an issue reflecting on the current object.
     */
    private static function getTraitMethods()
    {
        if (!empty(static::$traitMethods)) {
            return static::$traitMethods;
        }

        $classReflection = new ReflectionClass(static::class);
        $classTraits = array_unique(array_merge([WithTestnames::class], $classReflection->getTraitNames()));
        $traitMethods= [];

        foreach ($classTraits as $classTrait) {
            $reflection = new ReflectionClass($classTrait);
            $traitMethods[] = array_map(static function (\ReflectionMethod $method) {
                return $method->name;
            }, $reflection->getMethods());
        }

        static::$traitMethods = array_merge(...$traitMethods);

        return static::$traitMethods;
    }

    /**
     * Returns the name of the current data set, if any.
     *
     * @param TestCase $testCase The current test case.
     *
     * @return string The data set name.
     */
    protected function getDataName(TestCase $testCase)
    {
        if (method_exists($testCase, 'dataName')) {
            return (string)$testCase->dataName();
        }

        $candidates = array_reverse(class_parents($testCase));
        $testCaseClass = get_class($testCase);
        $candidates[$testCaseClass] = $testCaseClass;
        foreach (array_reverse($candidates) as $class) {
            try {
                $read = (string)ReflectionHelper::readPrivateProperty($testCase, 'dataName', $class);
            } catch (\ReflectionException $e) {
                continue;
            }

            return $read;
        }

        return '';
    }

    /**
     * Returns the current, progressive, counter value for a test case, method and data set.
     *
     * @param string $class  The class name.
     * @param string $method The method name.
     * @param string        string $dataSetName The data set name, if any.
     *
     * @return int The next counter value for the test case, method and data set.
     */
    protected function getCounterFor($class, $method, $dataSetName = '')
    {
        $method .= $dataSetName;

        if (isset(static::$counters[$class][$method])) {
            static::$counters[$class][$method] += 1;

            return static::$counters[$class][$method];
        }
        static::$counters[$class][$method] = 0;
        return 0;
    }

    /**
     * Returns the absolute path to the current test case directory.
     *
     * @param null $path A relative path to append to the test directory path.
     *
     * @return string The absolute path to the current test case directory with the optional path appended, if any.
     * @throws \ReflectionException If there's an issue reflecting on the current test case.
     */
    protected function getTestDir($path = null)
    {
        list($class, $function, $object) = $this->getTestClassFunctionObject();
        $classFile = (new ReflectionClass($class))->getFileName();
        $dir = dirname($classFile);

        return $path ? rtrim($dir, '\\/') . '/' . trim($path, '\\/') : $dir;
    }

    /**
     * Returns the test case class, function and object from the backtrace.
     *
     * @return array An array containing the current test case class, function and object.
     * @throws \ReflectionException If there's an issue reflecting on the current test case.
     */
    protected function getTestClassFunctionObject()
    {
        $traitMethods = self::getTraitMethods();
        $backtrace = array_values(array_filter(
            debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS
                | DEBUG_BACKTRACE_PROVIDE_OBJECT, 5),
            static function (array $backtraceEntry) use ($traitMethods) {
                return $backtraceEntry['class'] !== WithTestnames::class
                    && !in_array($backtraceEntry['function'], $traitMethods, true);
            }
        ));
        $class = $backtrace[0]['class'];
        $function = $backtrace[0]['function'];
        $object = $backtrace[0]['object'];

        return array($class, $function, $object);
    }
}
