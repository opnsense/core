<?php

/*
 * Copyright (C) 2024 Deciso B.V.
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 *    this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 * INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 * AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 * OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 */

namespace OPNsense\Mvc;

use Exception;
use ReflectionClass;
use ReflectionMethod;
use ReflectionException;
use OPNsense\Mvc\Exceptions\ClassNotFoundException;
use OPNsense\Mvc\Exceptions\MethodNotFoundException;
use OPNsense\Mvc\Exceptions\ParameterMismatchException;

class Dispatcher
{
    private string $namespace;
    private string $controller;
    private string $action;
    private array $parameters;
    private ReflectionClass $controllerClass;
    private array|string|null $returnedValue;

    /**
     * @param string $namespace full namespace to use (including vendor)
     * @param string $controller classname which implements this controller
     * @param string $action action handler to call
     * @param array $parameters list of supplied parameters
     */
    public function __construct(string $namespace, string $controller, string $action, array $parameters)
    {
        $this->namespace = $namespace;
        $this->controller = $controller;
        $this->action = $action;
        $this->parameters = $parameters;
    }

    /**
     * XXX: rename to getMethodName when phalcon is removed and return plain $this->action,
     *      next cleanup ApiControllerBase.
     * @return string action name
     */
    public function getActionName()
    {
        return substr($this->action, 0, strlen($this->action) - 6);
    }

    /**
     * Resolve controller class and inspect method to call, except when the target controller offers a __call
     * hook in which case we expect the target to offer proper error handling
     * @throws ClassNotFoundException when controller class can not be found
     * @throws MethodNotFoundException when controller method can not be found
     * @throws ParameterMismatchException when expected required parameters do not match offered ones
     */
    protected function resolve(): void
    {
        if (isset($this->controllerClass)) {
            // already resolved
            return;
        }
        $clsname = $this->namespace . "\\" . $this->controller;
        try {
            $this->controllerClass = new ReflectionClass($clsname);
        } catch (ReflectionException) {
            throw new ClassNotFoundException(sprintf("%s not found", $clsname));
        }
        if (!$this->controllerClass->isInstantiable()) {
            throw new ClassNotFoundException(sprintf("%s not found", $clsname));
        }
        if (!$this->controllerClass->hasMethod($this->action) && $this->controllerClass->hasMethod('__call')) {
            // dynamic class, we can't probe the method and its expected parameters.
            return;
        }
        if (!$this->controllerClass->hasMethod($this->action)) {
            throw new MethodNotFoundException(sprintf("%s -> %s not found", $clsname, $this->action));
        }
        try {
            $actionMethod = $this->controllerClass->getMethod($this->action);
        } catch (ReflectionException) {
            throw new MethodNotFoundException(sprintf("%s -> %s not found", $clsname, $this->action));
        }
        $pcount = 0;
        foreach ($actionMethod->getParameters() as $param) {
            if ($param->isOptional()) {
                break;
            }
            $pcount++;
        }
        if ($pcount > count($this->parameters)) {
            unset($this->controllerClass);
            throw new ParameterMismatchException(sprintf(
                "%s -> %s parameter mismatch (expected %d, got %d)",
                $clsname,
                $this->action,
                $pcount,
                count($this->parameters)
            ));
        }
    }

    /**
     * test if controller action method is callable with the parameters provided
     * @return bool
     */
    public function canExecute(): bool
    {
        try {
            $this->resolve();
            return true;
        } catch (Exception) {
            return false;
        }
    }

    /**
     * Dispatch (execute) controller action method
     * @param Request $request http request object
     * @param Response $response http response object
     * @param Session $session session object
     * @return bool
     * @throws ClassNotFoundException when controller class can not be found
     * @throws MethodNotFoundException when controller method can not be found
     * @throws ParameterMismatchException when expected required parameters do not match offered ones
     * @throws ReflectionException when invoke fails
     */
    public function dispatch(Request $request, Response $response, Session $session): bool
    {
        $this->resolve();

        $controller = $this->controllerClass->newInstance();
        $controller->session = $session;
        $controller->request = $request;
        $controller->response = $response;
        $controller->security = new Security($session, $request);

        $controller->initialize();

        if ($controller->beforeExecuteRoute($this) === false) {
            return false;
        }
        $this->returnedValue = $controller->{$this->action}(...$this->parameters);
        $session->close();
        $controller->afterExecuteRoute($this);
        return true;
    }

    /**
     * @return array|string|null response
     */
    public function getReturnedValue(): array|string|null
    {
        return $this->returnedValue;
    }

    /**
     * XXX: remove call from ControllerBase, seems like a workaround for a specific phalcon issue back in 2015
     * @return bool
     */
    public function wasForwarded(): bool
    {
        return false;
    }
}
