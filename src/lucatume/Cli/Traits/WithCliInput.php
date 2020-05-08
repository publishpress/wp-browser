<?php
/**
 * Provides methods to receive input from a CLI source.
 *
 * @package lucatume\Cli\Traits
 */

namespace lucatume\Cli\Traits;

/**
 * Trait WithCliInput
 *
 * @package lucatume\Cli\Traits
 */
trait WithCliInput
{
    /**
     * The input stream or array.
     *
     * @var false|resource|array
     */
    protected $inputStream = STDIN;

    /**
     * Asks the user a confirmation question.
     *
     * @param string $question The question to ask, including the question mark(s) if required.
     * @param bool $default The default answer.
     *
     * @return string The user answer.
     */
    public function confirm($question, $default = true)
    {
        $defaultAnswer = $default ? 'yes' : 'no';
        if ($this instanceof WithCliOutput) {
            $this->styledOutput(trim($question) . " ({$defaultAnswer}) ");
        } else {
            echo trim($question) . " ({$defaultAnswer}) ";
        }

        $answer = trim($this->readLine());

        if (empty($answer)) {
            return $default;
        }

        return (bool)preg_match('/^y/i', $answer);
    }

    /**
     * Reads one line from the current input stream.
     *
     * @return false|string The read line or `false` if there's no line to read.
     */
    protected function readLine()
    {
        if (is_resource($this->inputStream)) {
            return fgets($this->inputStream);
        }

        $inputArray = (array)$this->inputStream;

        return array_shift($inputArray);
    }

    /**
     * Sets the input stream the trait should use to read.
     *
     * @param array|resource|\ArrayAccess $resource Either the input stream or an array(-ish) to read the input from.
     */
    public function setInputStream($resource)
    {
        $this->inputStream = $resource;
    }
}
