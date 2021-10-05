<?php
namespace JakubOnderka\PhpParallelLint;

use JakubOnderka\PhpParallelLint\Contracts\SyntaxErrorCallback;
use JakubOnderka\PhpParallelLint\Process\LintProcess;
use JakubOnderka\PhpParallelLint\Process\PhpExecutable;
use JakubOnderka\PhpParallelLint\Process\SkipLintProcess;

class ParallelLint
{
    const STATUS_OK = 'ok',
        STATUS_SKIP = 'skip',
        STATUS_FAIL = 'fail',
        STATUS_ERROR = 'error';

    /** @var int */
    private $parallelJobs;

    /** @var PhpExecutable */
    private $phpExecutable;

    /** @var bool */
    private $aspTagsEnabled = false;

    /** @var bool */
    private $shortTagEnabled = false;

    /** @var callable */
    private $processCallback;

    /** @var bool */
    private $showDeprecated = false;

    /** @var SyntaxErrorCallback|null */
    private $syntaxErrorCallback = null;

    public function __construct(PhpExecutable $phpExecutable, $parallelJobs = 10)
    {
        $this->phpExecutable = $phpExecutable;
        $this->parallelJobs = $parallelJobs;
    }

    /**
     * @param array $files
     * @return Result
     * @throws \Exception
     */
    public function lint(array $files)
    {
        $startTime = microtime(true);

        $skipLintProcess = new SkipLintProcess($this->phpExecutable, $files);

        $processCallback = is_callable($this->processCallback) ? $this->processCallback : function () {
        };

        /**
         * @var LintProcess[] $running
         * @var LintProcess[] $waiting
         */
        $errors = $running = $waiting = array();
        $skippedFiles = $checkedFiles = array();

        while ($files || $running) {
            for ($i = count($running); $files && $i < $this->parallelJobs; $i++) {
                $file = array_shift($files);

                if ($skipLintProcess->isSkipped($file) === true) {
                    $skippedFiles[] = $file;
                    $processCallback(self::STATUS_SKIP, $file);
                } else {
                    $running[$file] = new LintProcess(
                        $this->phpExecutable,
                        $file,
                        $this->aspTagsEnabled,
                        $this->shortTagEnabled,
                        $this->showDeprecated
                    );
                }
            }

            $skipLintProcess->getChunk();
            usleep(100);

            foreach ($running as $file => $process) {
                if ($process->isFinished()) {
                    unset($running[$file]);

                    $skipStatus = $skipLintProcess->isSkipped($file);
                    if ($skipStatus === null) {
                        $waiting[$file] = $process;

                    } else if ($skipStatus === true) {
                        $skippedFiles[] = $file;
                        $processCallback(self::STATUS_SKIP, $file);

                    } else if ($process->containsError()) {
                        $checkedFiles[] = $file;
                        $errors[] = $this->triggerSyntaxErrorCallback(new SyntaxError($file, $process->getSyntaxError()));
                        $processCallback(self::STATUS_ERROR, $file);

                    } else if ($process->isSuccess()) {
                        $checkedFiles[] = $file;
                        $processCallback(self::STATUS_OK, $file);


                    } else {
                        $errors[] = new Error($file, $process->getOutput());
                        $processCallback(self::STATUS_FAIL, $file);
                    }
                }
            }
        }

        if (!empty($waiting)) {
            $skipLintProcess->waitForFinish();

            if ($skipLintProcess->isFail()) {
                $message = "Error in skip-linting.php process\nError output: {$skipLintProcess->getErrorOutput()}";
                throw new \Exception($message);
            }

            foreach ($waiting as $file => $process) {
                $skipStatus = $skipLintProcess->isSkipped($file);
                if ($skipStatus === null) {
                    throw new \Exception("File $file has empty skip status. Please contact the author of PHP Parallel Lint.");

                } else if ($skipStatus === true) {
                    $skippedFiles[] = $file;
                    $processCallback(self::STATUS_SKIP, $file);

                } else if ($process->isSuccess()) {
                    $checkedFiles[] = $file;
                    $processCallback(self::STATUS_OK, $file);

                } else if ($process->containsError()) {
                    $checkedFiles[] = $file;
                    $errors[] = $this->triggerSyntaxErrorCallback(new SyntaxError($file, $process->getSyntaxError()));
                    $processCallback(self::STATUS_ERROR, $file);

                } else {
                    $errors[] = new Error($file, $process->getOutput());
                    $processCallback(self::STATUS_FAIL, $file);
                }
            }
        }

        $testTime = microtime(true) - $startTime;

        return new Result($errors, $checkedFiles, $skippedFiles, $testTime);
    }

    /**
     * @return int
     */
    public function getParallelJobs()
    {
        return $this->parallelJobs;
    }

    /**
     * @param int $parallelJobs
     * @return ParallelLint
     */
    public function setParallelJobs($parallelJobs)
    {
        $this->parallelJobs = $parallelJobs;

        return $this;
    }

    /**
     * @return string
     */
    public function getPhpExecutable()
    {
        return $this->phpExecutable;
    }

    /**
     * @param string $phpExecutable
     * @return ParallelLint
     */
    public function setPhpExecutable($phpExecutable)
    {
        $this->phpExecutable = $phpExecutable;

        return $this;
    }

    /**
     * @return callable
     */
    public function getProcessCallback()
    {
        return $this->processCallback;
    }

    /**
     * @param callable $processCallback
     * @return ParallelLint
     */
    public function setProcessCallback($processCallback)
    {
        $this->processCallback = $processCallback;

        return $this;
    }

    /**
     * @return boolean
     */
    public function isAspTagsEnabled()
    {
        return $this->aspTagsEnabled;
    }

    /**
     * @param boolean $aspTagsEnabled
     * @return ParallelLint
     */
    public function setAspTagsEnabled($aspTagsEnabled)
    {
        $this->aspTagsEnabled = $aspTagsEnabled;

        return $this;
    }

    /**
     * @return boolean
     */
    public function isShortTagEnabled()
    {
        return $this->shortTagEnabled;
    }

    /**
     * @param boolean $shortTagEnabled
     * @return ParallelLint
     */
    public function setShortTagEnabled($shortTagEnabled)
    {
        $this->shortTagEnabled = $shortTagEnabled;

        return $this;
    }

    /**
     * @return boolean
     */
    public function isShowDeprecated()
    {
        return $this->showDeprecated;
    }

    /**
     * @param $showDeprecated
     * @return ParallelLint
     */
    public function setShowDeprecated($showDeprecated)
    {
        $this->showDeprecated = $showDeprecated;

        return $this;
    }

    public function triggerSyntaxErrorCallback($syntaxError)
    {
        if ($this->syntaxErrorCallback === null) {
            return $syntaxError;
        }

        return $this->syntaxErrorCallback->errorFound($syntaxError);
    }

    /**
     * @param SyntaxErrorCallback|null $syntaxErrorCallback
     * @return ParallelLint
     */
    public function setSyntaxErrorCallback($syntaxErrorCallback)
    {
        $this->syntaxErrorCallback = $syntaxErrorCallback;

        return $this;
    }
}
