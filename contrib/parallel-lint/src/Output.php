<?php
namespace JakubOnderka\PhpParallelLint;

interface Output
{
    public function __construct(IWriter $writer);

    public function ok();

    public function skip();

    public function error();

    public function fail();

    public function setTotalFileCount($count);

    public function writeHeader($phpVersion, $parallelJobs, $hhvmVersion = null);

    public function writeResult(Result $result, ErrorFormatter $errorFormatter, $ignoreFails);
}

class JsonOutput implements Output
{
    /** @var IWriter */
    protected $writer;

    /** @var int */
    protected $phpVersion;

    /** @var int */
    protected $parallelJobs;

    /** @var string */
    protected $hhvmVersion;

    /**
     * @param IWriter $writer
     */
    public function __construct(IWriter $writer)
    {
        $this->writer = $writer;
    }

    public function ok()
    {

    }

    public function skip()
    {

    }

    public function error()
    {

    }

    public function fail()
    {

    }

    public function setTotalFileCount($count)
    {

    }

    public function writeHeader($phpVersion, $parallelJobs, $hhvmVersion = null)
    {
        $this->phpVersion = $phpVersion;
        $this->parallelJobs = $parallelJobs;
        $this->hhvmVersion = $hhvmVersion;
    }

    public function writeResult(Result $result, ErrorFormatter $errorFormatter, $ignoreFails)
    {
        echo json_encode(array(
            'phpVersion' => $this->phpVersion,
            'hhvmVersion' => $this->hhvmVersion,
            'parallelJobs' => $this->parallelJobs,
            'results' => $result,
        ));
    }
}

class GitLabOutput implements Output
{
    /** @var IWriter */
    protected $writer;

    /**
     * @param IWriter $writer
     */
    public function __construct(IWriter $writer)
    {
        $this->writer = $writer;
    }

    public function ok()
    {

    }

    public function skip()
    {

    }

    public function error()
    {

    }

    public function fail()
    {

    }

    public function setTotalFileCount($count)
    {

    }

    public function writeHeader($phpVersion, $parallelJobs, $hhvmVersion = null)
    {

    }

    public function writeResult(Result $result, ErrorFormatter $errorFormatter, $ignoreFails)
    {
        $errors = array();
        foreach ($result->getErrors() as $error) {
            $message = $error->getMessage();
            $line = 1;
            if ($error instanceof SyntaxError) {
                $line = $error->getLine();
            }
            $filePath = $error->getFilePath();
            $result = array(
                'type' => 'issue',
                'check_name' => 'Parse error',
                'description' => $message,
                'categories' => 'Style',
                'fingerprint' => md5($filePath . $message . $line),
                'severity' => 'minor',
                'location' => array(
                    'path' => $filePath,
                    'lines' => array(
                        'begin' => $line,
                    ),
                ),
            );
            array_push($errors, $result);
        }

        $string = json_encode($errors) . PHP_EOL;
        $this->writer->write($string);
    }
}

class TextOutput implements Output
{
    const TYPE_DEFAULT = 'default',
        TYPE_SKIP = 'skip',
        TYPE_ERROR = 'error',
        TYPE_FAIL = 'fail',
        TYPE_OK = 'ok';

    /** @var int */
    public $filesPerLine = 60;

    /** @var bool */
    public $showProgress = true;

    /** @var int */
    protected $checkedFiles;

    /** @var int */
    protected $totalFileCount;

    /** @var IWriter */
    protected $writer;

    /**
     * @param IWriter $writer
     */
    public function __construct(IWriter $writer)
    {
        $this->writer = $writer;
    }

    public function ok()
    {
        $this->writeMark(self::TYPE_OK);
    }

    public function skip()
    {
        $this->writeMark(self::TYPE_SKIP);
    }

    public function error()
    {
        $this->writeMark(self::TYPE_ERROR);
    }

    public function fail()
    {
        $this->writeMark(self::TYPE_FAIL);
    }

    /**
     * @param string $string
     * @param string $type
     */
    public function write($string, $type = self::TYPE_DEFAULT)
    {
        $this->writer->write($string);
    }

    /**
     * @param string|null $line
     * @param string $type
     */
    public function writeLine($line = null, $type = self::TYPE_DEFAULT)
    {
        $this->write($line, $type);
        $this->writeNewLine();
    }

    /**
     * @param int $count
     */
    public function writeNewLine($count = 1)
    {
        $this->write(str_repeat(PHP_EOL, $count));
    }

    /**
     * @param int $count
     */
    public function setTotalFileCount($count)
    {
        $this->totalFileCount = $count;
    }

    /**
     * @param int $phpVersion
     * @param int $parallelJobs
     * @param string $hhvmVersion
     */
    public function writeHeader($phpVersion, $parallelJobs, $hhvmVersion = null)
    {
        $this->write("PHP {$this->phpVersionIdToString($phpVersion)} | ");

        if ($hhvmVersion) {
            $this->write("HHVM $hhvmVersion | ");
        }

        if ($parallelJobs === 1) {
            $this->writeLine("1 job");
        } else {
            $this->writeLine("{$parallelJobs} parallel jobs");
        }
    }

    /**
     * @param Result $result
     * @param ErrorFormatter $errorFormatter
     * @param bool $ignoreFails
     */
    public function writeResult(Result $result, ErrorFormatter $errorFormatter, $ignoreFails)
    {
        if ($this->showProgress) {
            if ($this->checkedFiles % $this->filesPerLine !== 0) {
                $rest = $this->filesPerLine - ($this->checkedFiles % $this->filesPerLine);
                $this->write(str_repeat(' ', $rest));
                $this->writePercent();
            }

            $this->writeNewLine(2);
        }

        $testTime = round($result->getTestTime(), 1);
        $message = "Checked {$result->getCheckedFilesCount()} files in $testTime ";
        $message .= $testTime == 1 ? 'second' : 'seconds';

        if ($result->getSkippedFilesCount() > 0) {
            $message .= ", skipped {$result->getSkippedFilesCount()} ";
            $message .= ($result->getSkippedFilesCount() === 1 ? 'file' : 'files');
        }

        $this->writeLine($message);

        if (!$result->hasSyntaxError()) {
            $message = "No syntax error found";
        } else {
            $message = "Syntax error found in {$result->getFilesWithSyntaxErrorCount()} ";
            $message .= ($result->getFilesWithSyntaxErrorCount() === 1 ? 'file' : 'files');
        }

        if ($result->hasFilesWithFail()) {
            $message .= ", failed to check {$result->getFilesWithFailCount()} ";
            $message .= ($result->getFilesWithFailCount() === 1 ? 'file' : 'files');

            if ($ignoreFails) {
                $message .= ' (ignored)';
            }
        }

        $hasError = $ignoreFails ? $result->hasSyntaxError() : $result->hasError();
        $this->writeLine($message, $hasError ? self::TYPE_ERROR : self::TYPE_OK);

        if ($result->hasError()) {
            $this->writeNewLine();
            foreach ($result->getErrors() as $error) {
                $this->writeLine(str_repeat('-', 60));
                $this->writeLine($errorFormatter->format($error));
            }
        }
    }

    protected function writeMark($type)
    {
        ++$this->checkedFiles;

        if ($this->showProgress) {
            if ($type === self::TYPE_OK) {
                $this->writer->write('.');

            } else if ($type === self::TYPE_SKIP) {
                $this->write('S', self::TYPE_SKIP);

            } else if ($type === self::TYPE_ERROR) {
                $this->write('X', self::TYPE_ERROR);

            } else if ($type === self::TYPE_FAIL) {
                $this->writer->write('-');
            }

            if ($this->checkedFiles % $this->filesPerLine === 0) {
                $this->writePercent();
            }
        }
    }

    protected function writePercent()
    {
        $percent = floor($this->checkedFiles / $this->totalFileCount * 100);
        $current = $this->stringWidth($this->checkedFiles, strlen($this->totalFileCount));
        $this->writeLine(" $current/$this->totalFileCount ($percent %)");
    }

    /**
     * @param string $input
     * @param int $width
     * @return string
     */
    protected function stringWidth($input, $width = 3)
    {
        $multiplier = $width - strlen($input);
        return str_repeat(' ', $multiplier > 0 ? $multiplier : 0) . $input;
    }

    /**
     * @param int $phpVersionId
     * @return string
     */
    protected function phpVersionIdToString($phpVersionId)
    {
        $releaseVersion = (int) substr($phpVersionId, -2, 2);
        $minorVersion = (int) substr($phpVersionId, -4, 2);
        $majorVersion = (int) substr($phpVersionId, 0, strlen($phpVersionId) - 4);

        return "$majorVersion.$minorVersion.$releaseVersion";
    }
}

class CheckstyleOutput implements Output
{
    private $writer;

    public function __construct(IWriter $writer)
    {
        $this->writer = $writer;
    }

    public function ok()
    {
    }

    public function skip()
    {
    }

    public function error()
    {
    }

    public function fail()
    {
    }

    public function setTotalFileCount($count)
    {
    }

    public function writeHeader($phpVersion, $parallelJobs, $hhvmVersion = null)
    {
        $this->writer->write('<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL);
    }

    public function writeResult(Result $result, ErrorFormatter $errorFormatter, $ignoreFails)
    {
        $this->writer->write('<checkstyle>' . PHP_EOL);
        $errors = array();

        foreach ($result->getErrors() as $error) {
            $message = $error->getMessage();
            if ($error instanceof SyntaxError) {
                $line = $error->getLine();
                $source = "Syntax Error";
            } else {
                $line = 1;
                $source = "Linter Error";
            }

            $errors[$error->getShortFilePath()][] = array(
                'message' => $message,
                'line' => $line,
                'source' => $source
            );
        }

        foreach ($errors as $file => $fileErrors) {
            $this->writer->write(sprintf('    <file name="%s">', $file) . PHP_EOL);
            foreach ($fileErrors as $fileError) {
                $this->writer->write(
                    sprintf(
                        '        <error line="%d" severity="ERROR" message="%s" source="%s" />',
                        $fileError['line'],
                        htmlspecialchars($fileError['message'], ENT_COMPAT, 'UTF-8'),
                        $fileError['source']
                    ) .
                    PHP_EOL
                );
            }
            $this->writer->write('    </file>' . PHP_EOL);
        }

        $this->writer->write('</checkstyle>' . PHP_EOL);
    }
}

class TextOutputColored extends TextOutput
{
    /** @var \PHP_Parallel_Lint\PhpConsoleColor\ConsoleColor|\JakubOnderka\PhpConsoleColor\ConsoleColor */
    private $colors;

    public function __construct(IWriter $writer, $colors = Settings::AUTODETECT)
    {
        parent::__construct($writer);

        if (class_exists('\PHP_Parallel_Lint\PhpConsoleColor\ConsoleColor')) {
            $this->colors = new \PHP_Parallel_Lint\PhpConsoleColor\ConsoleColor();
            $this->colors->setForceStyle($colors === Settings::FORCED);
        } else if (class_exists('\JakubOnderka\PhpConsoleColor\ConsoleColor')) {
            $this->colors = new \JakubOnderka\PhpConsoleColor\ConsoleColor();
            $this->colors->setForceStyle($colors === Settings::FORCED);
        }
    }

    /**
     * @param string $string
     * @param string $type
     * @throws \PHP_Parallel_Lint\PhpConsoleColor\InvalidStyleException|\JakubOnderka\PhpConsoleColor\InvalidStyleException
     */
    public function write($string, $type = self::TYPE_DEFAULT)
    {
        if (
            !$this->colors instanceof \PHP_Parallel_Lint\PhpConsoleColor\ConsoleColor
            && !$this->colors instanceof \JakubOnderka\PhpConsoleColor\ConsoleColor
        ) {
            parent::write($string, $type);
        } else {
            switch ($type) {
                case self::TYPE_OK:
                    parent::write($this->colors->apply('bg_green', $string));
                    break;

                case self::TYPE_SKIP:
                    parent::write($this->colors->apply('bg_yellow', $string));
                    break;

                case self::TYPE_ERROR:
                    parent::write($this->colors->apply('bg_red', $string));
                    break;

                default:
                    parent::write($string);
            }
        }
    }
}

interface IWriter
{
    /**
     * @param string $string
     */
    public function write($string);
}

class NullWriter implements IWriter
{
    /**
     * @param string $string
     */
    public function write($string)
    {

    }
}

class ConsoleWriter implements IWriter
{
    /**
     * @param string $string
     */
    public function write($string)
    {
        echo $string;
    }
}

class FileWriter implements IWriter
{
    /** @var string */
    protected $logFile;

    /** @var string */
    protected $buffer;

    public function __construct($logFile)
    {
        $this->logFile = $logFile;
    }

    public function write($string)
    {
        $this->buffer .= $string;
    }

    public function __destruct()
    {
        file_put_contents($this->logFile, $this->buffer);
    }
}

class MultipleWriter implements IWriter
{
    /** @var IWriter[] */
    protected $writers;

    /**
     * @param IWriter[] $writers
     */
    public function __construct(array $writers)
    {
        foreach ($writers as $writer) {
            $this->addWriter($writer);
        }
    }

    /**
     * @param IWriter $writer
     */
    public function addWriter(IWriter $writer)
    {
        $this->writers[] = $writer;
    }

    /**
     * @param $string
     */
    public function write($string)
    {
        foreach ($this->writers as $writer) {
            $writer->write($string);
        }
    }
}
