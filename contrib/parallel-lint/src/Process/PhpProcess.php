<?php

namespace JakubOnderka\PhpParallelLint\Process;

class PhpProcess extends Process
{
    /**
     * @param PhpExecutable $phpExecutable
     * @param array $parameters
     * @param string|null $stdIn
     * @throws \JakubOnderka\PhpParallelLint\RunTimeException
     */
    public function __construct(PhpExecutable $phpExecutable, array $parameters = array(), $stdIn = null)
    {
        $constructedParameters = $this->constructParameters($parameters, $phpExecutable->isIsHhvmType());
        parent::__construct($phpExecutable->getPath(), $constructedParameters, $stdIn);
    }

    /**
     * @param array $parameters
     * @param bool $isHhvm
     * @return array
     */
    private function constructParameters(array $parameters, $isHhvm)
    {
        // Always ignore PHP startup errors ("Unable to load library...") in sub-processes.
        array_unshift($parameters, '-d display_startup_errors=0');

        if ($isHhvm) {
            array_unshift($parameters, '-php');
        }

        return $parameters;
    }
}
