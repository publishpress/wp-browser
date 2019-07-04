<?php
/**
 * Provides common utils and functions to manipulate strings.
 *
 * @package tad\WPBrowser\Utils
 */

namespace tad\WPBrowser\Utils;

/**
 * Class Str
 *
 * @package tad\WPBrowser\Utils
 */
class Str
{
    /**
     * Search and replace a string recursively in its normal and urlencoded form.
     *
     * @param string       $search  The string to search for, the needle.
     * @param string       $replace The replacement string the needle will be replaced with.
     * @param string|array $subject Either a string or an array to recursively search.
     *
     * @return string|array The replaced string, or array, depending on the original type.
     */
    public static function replaceRecursive($search, $replace, $subject)
    {
        $subjectTypeIsArray = is_array($subject);
        $replaced = [];
        foreach ((array)$subject as $key => $value) {
            if (is_array($value)) {
                $replaced[$key] = static::replaceRecursive($search, $replace, $value);
            } else {
                $replaced[$key] = str_replace(
                    array($search, urlencode($search)),
                    [$replace, urldecode($replace)],
                    $value
                );
            }
        }

        return $subjectTypeIsArray ? $replaced : reset($replaced);
    }
}
