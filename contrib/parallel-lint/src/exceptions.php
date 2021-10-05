<?php
namespace JakubOnderka\PhpParallelLint;

use ReturnTypeWillChange;

class Exception extends \Exception implements \JsonSerializable
{
    #[ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return array(
            'type' => get_class($this),
            'message' => $this->getMessage(),
            'code' => $this->getCode(),
        );
    }
}

class RunTimeException extends Exception
{

}

class InvalidArgumentException extends Exception
{
    protected $argument;

    public function __construct($argument)
    {
        $this->argument = $argument;
        $this->message = "Invalid argument $argument";
    }

    public function getArgument()
    {
        return $this->argument;
    }
}

class NotExistsPathException extends Exception
{
    protected $path;

    public function __construct($path)
    {
        $this->path = $path;
        $this->message = "Path '$path' not found";
    }

    public function getPath()
    {
        return $this->path;
    }
}

class NotExistsClassException extends Exception
{
    protected $className;
    protected $fileName;

    public function __construct($className, $fileName)
    {
        $this->className = $className;
        $this->fileName = $fileName;
        $this->message = "Class with name '$className' does not exists in file '$fileName'";
    }

    public function getClassName()
    {
        return $this->className;
    }

    public function getFileName()
    {
        return $this->fileName;
    }
}

class NotImplementCallbackException extends Exception
{
    protected $className;

    public function __construct($className)
    {
        $this->className = $className;
        $this->message = "Class '$className' does not implement SyntaxErrorCallback interface.";
    }

    public function getClassName()
    {
        return $this->className;
    }
}
