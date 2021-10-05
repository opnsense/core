<?php
namespace JakubOnderka\PhpParallelLint\Process;

use JakubOnderka\PhpParallelLint\RunTimeException;

class LintProcess extends PhpProcess
{
    const FATAL_ERROR = 'Fatal error';
    const PARSE_ERROR = 'Parse error';
    const DEPRECATED_ERROR = 'Deprecated:';

    /**
     * @var bool
     */
    private $showDeprecatedErrors;

    /**
     * @param PhpExecutable $phpExecutable
     * @param string $fileToCheck Path to file to check
     * @param bool $aspTags
     * @param bool $shortTag
     * @param bool $deprecated
     * @throws RunTimeException
     */
    public function __construct(PhpExecutable $phpExecutable, $fileToCheck, $aspTags = false, $shortTag = false, $deprecated = false)
    {
        if (empty($fileToCheck)) {
            throw new \InvalidArgumentException("File to check must be set.");
        }

        $parameters = array(
            '-d asp_tags=' . ($aspTags ? 'On' : 'Off'),
            '-d short_open_tag=' . ($shortTag ? 'On' : 'Off'),
            '-d error_reporting=E_ALL',
            '-n',
            '-l',
            $fileToCheck,
        );

        $this->showDeprecatedErrors = $deprecated;
        parent::__construct($phpExecutable, $parameters);
    }

    /**
     * @return bool
     * @throws
     */
    public function containsError()
    {
        return $this->containsParserError($this->getOutput()) ||
            $this->containsFatalError($this->getOutput()) ||
            $this->containsDeprecatedError($this->getOutput());
    }

    /**
     * @return string
     * @throws RunTimeException
     */
    public function getSyntaxError()
    {
        if ($this->containsError()) {
            // Look for fatal errors first
            foreach (explode("\n", $this->getOutput()) as $line) {
                if ($this->containsFatalError($line)) {
                    return $line;
                }
            }

            // Look for parser errors second
            foreach (explode("\n", $this->getOutput()) as $line) {
                if ($this->containsParserError($line)) {
                    return $line;
                }
            }

            // Look for deprecated errors third
            foreach (explode("\n", $this->getOutput()) as $line) {
                if ($this->containsDeprecatedError($line)) {
                    return $line;
                }
            }

            throw new RunTimeException("The output '{$this->getOutput()}' does not contain Parse or Syntax errors");
        }

        return false;
    }

    /**
     * @return bool
     * @throws RunTimeException
     */
    public function isFail()
    {
        return defined('PHP_WINDOWS_VERSION_MAJOR') ? $this->getStatusCode() === 1 : parent::isFail();
    }

    /**
     * @return bool
     * @throws RunTimeException
     */
    public function isSuccess()
    {
        return $this->getStatusCode() === 0;
    }

    /**
     * @param string $string
     * @return bool
     */
    private function containsParserError($string)
    {
        return strpos($string, self::PARSE_ERROR) !== false;
    }

    /**
     * @param string $string
     * @return bool
     */
    private function containsFatalError($string)
    {
        return strpos($string, self::FATAL_ERROR) !== false;
    }

    /**
     * @param string $string
     * @return bool
     */
    private function containsDeprecatedError($string)
    {
        if ($this->showDeprecatedErrors === false) {
            return false;
        }

        return strpos($string, self::DEPRECATED_ERROR) !== false;
    }
}
