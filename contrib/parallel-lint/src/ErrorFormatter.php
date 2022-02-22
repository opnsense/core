<?php
namespace JakubOnderka\PhpParallelLint;

use JakubOnderka\PhpConsoleColor\ConsoleColor as OldConsoleColor;
use JakubOnderka\PhpConsoleHighlighter\Highlighter as OldHighlighter;
use PHP_Parallel_Lint\PhpConsoleColor\ConsoleColor;
use PHP_Parallel_Lint\PhpConsoleHighlighter\Highlighter;

class ErrorFormatter
{
    /** @var string */
    private $useColors;

    /** @var bool */
    private $forceColors;

    /** @var bool */
    private $translateTokens;

    public function __construct($useColors = Settings::AUTODETECT, $translateTokens = false, $forceColors = false)
    {
        $this->useColors = $useColors;
        $this->forceColors = $forceColors;
        $this->translateTokens = $translateTokens;
    }

    /**
     * @param Error $error
     * @return string
     */
    public function format(Error $error)
    {
        if ($error instanceof SyntaxError) {
            return $this->formatSyntaxErrorMessage($error);
        } else {
            if ($error->getMessage()) {
                return $error->getMessage();
            } else {
                return "Unknown error for file '{$error->getFilePath()}'.";
            }
        }
    }

    /**
     * @param SyntaxError $error
     * @param bool $withCodeSnipped
     * @return string
     */
    public function formatSyntaxErrorMessage(SyntaxError $error, $withCodeSnipped = true)
    {
        $string = "Parse error: {$error->getShortFilePath()}";

        if ($error->getLine()) {
            $onLine = $error->getLine();
            $string .= ":$onLine" . PHP_EOL;

            if ($withCodeSnipped) {
                if ($this->useColors !== Settings::DISABLED) {
                    $string .= $this->getColoredCodeSnippet($error->getFilePath(), $onLine);
                } else {
                    $string .= $this->getCodeSnippet($error->getFilePath(), $onLine);
                }
            }
        }

        $string .= $error->getNormalizedMessage($this->translateTokens);

        if ($error->getBlame()) {
            $blame = $error->getBlame();
            $shortCommitHash = substr($blame->commitHash, 0, 8);
            $dateTime = $blame->datetime->format('c');
            $string .= PHP_EOL . "Blame {$blame->name} <{$blame->email}>, commit '$shortCommitHash' from $dateTime";
        }

        return $string;
    }

    /**
     * @param string $filePath
     * @param int $lineNumber
     * @param int $linesBefore
     * @param int $linesAfter
     * @return string
     */
    protected function getCodeSnippet($filePath, $lineNumber, $linesBefore = 2, $linesAfter = 2)
    {
        $lines = file($filePath);

        $offset = $lineNumber - $linesBefore - 1;
        $offset = max($offset, 0);
        $length = $linesAfter + $linesBefore + 1;
        $lines = array_slice($lines, $offset, $length, $preserveKeys = true);

        end($lines);
        $lineStrlen = strlen(key($lines) + 1);

        $snippet = '';
        foreach ($lines as $i => $line) {
            $snippet .= ($lineNumber === $i + 1 ? '  > ' : '    ');
            $snippet .= str_pad($i + 1, $lineStrlen, ' ', STR_PAD_LEFT) . '| ' . rtrim($line) . PHP_EOL;
        }

        return $snippet;
    }

    /**
     * @param string $filePath
     * @param int $lineNumber
     * @param int $linesBefore
     * @param int $linesAfter
     * @return string
     */
    protected function getColoredCodeSnippet($filePath, $lineNumber, $linesBefore = 2, $linesAfter = 2)
    {
        if (
            class_exists('\PHP_Parallel_Lint\PhpConsoleHighlighter\Highlighter')
            && class_exists('\PHP_Parallel_Lint\PhpConsoleColor\ConsoleColor')
        ) {
            // Highlighter and ConsoleColor 1.0+.
            $colors = new ConsoleColor();
            $colors->setForceStyle($this->forceColors);
            $highlighter = new Highlighter($colors);
        } else if (
            class_exists('\JakubOnderka\PhpConsoleHighlighter\Highlighter')
            && class_exists('\JakubOnderka\PhpConsoleColor\ConsoleColor')
        ) {
            // Highlighter and ConsoleColor < 1.0.
            $colors = new OldConsoleColor();
            $colors->setForceStyle($this->forceColors);
            $highlighter = new OldHighlighter($colors);
        }

        if (isset($colors, $highlighter) === false) {
            return $this->getCodeSnippet($filePath, $lineNumber, $linesBefore, $linesAfter);
        }

        $fileContent = file_get_contents($filePath);
        return $highlighter->getCodeSnippet($fileContent, $lineNumber, $linesBefore, $linesAfter);
    }
}
