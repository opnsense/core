<?php
namespace JakubOnderka\PhpParallelLint\Contracts;

use JakubOnderka\PhpParallelLint\SyntaxError;

interface SyntaxErrorCallback
{
    /**
     * @param SyntaxError $error
     * @return SyntaxError
     */
    public function errorFound(SyntaxError $error);
}
