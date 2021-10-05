<?php
namespace JakubOnderka\PhpParallelLint;

use ReturnTypeWillChange;

class Result implements \JsonSerializable
{
    /** @var Error[] */
    private $errors;

    /** @var array */
    private $checkedFiles;

    /** @var array */
    private $skippedFiles;

    /** @var float */
    private $testTime;

    /**
     * @param Error[] $errors
     * @param array $checkedFiles
     * @param array $skippedFiles
     * @param float $testTime
     */
    public function __construct(array $errors, array $checkedFiles, array $skippedFiles, $testTime)
    {
        $this->errors = $errors;
        $this->checkedFiles = $checkedFiles;
        $this->skippedFiles = $skippedFiles;
        $this->testTime = $testTime;
    }

    /**
     * @return Error[]
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * @return bool
     */
    public function hasError()
    {
        return !empty($this->errors);
    }

    /**
     * @return array
     */
    public function getFilesWithFail()
    {
        $filesWithFail = array();
        foreach ($this->errors as $error) {
            if (!$error instanceof SyntaxError) {
                $filesWithFail[] = $error->getFilePath();
            }
        }

        return $filesWithFail;
    }

    /**
     * @return int
     */
    public function getFilesWithFailCount()
    {
        return count($this->getFilesWithFail());
    }

    /**
     * @return bool
     */
    public function hasFilesWithFail()
    {
        return $this->getFilesWithFailCount() !== 0;
    }

    /**
     * @return array
     */
    public function getCheckedFiles()
    {
        return $this->checkedFiles;
    }

    /**
     * @return int
     */
    public function getCheckedFilesCount()
    {
        return count($this->checkedFiles);
    }

    /**
     * @return array
     */
    public function getSkippedFiles()
    {
        return $this->skippedFiles;
    }

    /**
     * @return int
     */
    public function getSkippedFilesCount()
    {
        return count($this->skippedFiles);
    }

    /**
     * @return array
     */
    public function getFilesWithSyntaxError()
    {
        $filesWithSyntaxError = array();
        foreach ($this->errors as $error) {
            if ($error instanceof SyntaxError) {
                $filesWithSyntaxError[] = $error->getFilePath();
            }
        }

        return $filesWithSyntaxError;
    }

    /**
     * @return int
     */
    public function getFilesWithSyntaxErrorCount()
    {
        return count($this->getFilesWithSyntaxError());
    }

    /**
     * @return bool
     */
    public function hasSyntaxError()
    {
        return $this->getFilesWithSyntaxErrorCount() !== 0;
    }

    /**
     * @return float
     */
    public function getTestTime()
    {
        return $this->testTime;
    }

    /**
     * (PHP 5 &gt;= 5.4.0)<br/>
     * Specify data which should be serialized to JSON
     * @link http://php.net/manual/en/jsonserializable.jsonserialize.php
     * @return mixed data which can be serialized by <b>json_encode</b>,
     * which is a value of any type other than a resource.
     */
    #[ReturnTypeWillChange]
    function jsonSerialize()
    {
        return array(
            'checkedFiles' => $this->getCheckedFiles(),
            'filesWithSyntaxError' => $this->getFilesWithSyntaxError(),
            'skippedFiles' => $this->getSkippedFiles(),
            'errors' => $this->getErrors(),
        );
    }


}