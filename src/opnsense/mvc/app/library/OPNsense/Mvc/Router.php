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

use DirectoryIterator;
use OPNsense\Core\AppConfig;
use OPNsense\Mvc\Exceptions\ClassNotFoundException;
use OPNsense\Mvc\Exceptions\InvalidUriException;
use OPNsense\Mvc\Exceptions\MethodNotFoundException;
use OPNsense\Mvc\Exceptions\ParameterMismatchException;
use ReflectionException;

class Router
{
    private string $prefix;
    private ?string $namespace_suffix;

    /**
     * @param string $prefix uri prefix to use, e.g /api/, /ui/
     * @param string|null $namespace_suffix optional namespace suffix (e.g. Api for api controllers)
     */
    public function __construct(string $prefix, ?string $namespace_suffix = null)
    {
        $this->prefix = $prefix;
        $this->namespace_suffix = $namespace_suffix;
    }

    /**
     * probe for namespace in specified AppConfig controllersDir
     * @param string $namespace base namespace to search for (without vendor)
     * @param string $controller controller class name
     * @return string|null namespace with vendor when found
     */
    private function resolveNamespace(?string $namespace, ?string $controller): string|null
    {
        if (empty($namespace) || empty($controller)) {
            return null;
        }
        $appconfig = new AppConfig();
        foreach ((array)$appconfig->application->controllersDir as $controllersDir) {
            // sort OPNsense namespace on top
            $dirs = glob($controllersDir . "/*", GLOB_ONLYDIR);
            usort($dirs, function ($a, $b) {
                if (basename($b) == 'OPNsense') {
                    return 1;
                } else {
                    return strcasecmp($a, $b);
                }
            });
            foreach ($dirs as $dirname) {
                $basename = basename($dirname);
                if (!is_dir("$dirname/$namespace")) {
                    /* In an ideal world, our namespaces are case sensitive and follow the snake to camel convention.
                       Since this is not always the case, try to perform a case-insensitive search if the namespace
                       does not exist in the expected case. (Phalcon backwards compatibility)
                     */
                    foreach (new DirectoryIterator($dirname) as $fileinfo) {
                        if ($fileinfo->isDir() && !strcasecmp($fileinfo->getFileName(), $namespace)) {
                            $namespace = $fileinfo->getFileName();
                            break;
                        }
                    }
                }
                $new_namespace = "$basename\\$namespace";
                if (!empty($this->namespace_suffix)) {
                    $expected_filename = "$dirname/$namespace/$this->namespace_suffix/$controller.php";
                    $new_namespace .= "\\" . $this->namespace_suffix;
                } else {
                    $expected_filename = "$dirname/$namespace/$controller.php";
                }
                if (is_file($expected_filename)) {
                    return  $new_namespace;
                }
            }
        }
        return null;
    }

    /**
     * Route a request
     * @param string $uri
     * @param array $default list of routing defaults (controller, acount)
     * @return Response to be rendered
     * @throws ClassNotFoundException
     * @throws MethodNotFoundException
     * @throws ParameterMismatchException
     * @throws InvalidUriException
     * @throws ReflectionException when invoke fails
     */
    public function routeRequest(string $uri, array $defaults = []): Response
    {
        $path = parse_url('/' . ltrim($uri, '/'))['path'] ?? '';

        if (!str_starts_with($path, $this->prefix)) {
            throw new InvalidUriException("Invalid route path: " . $uri);
        }

        // extract target (base)namespace, controller and action
        $targetAndParameters = $this->parsePath(substr($path, strlen($this->prefix)), $defaults);

        $controller = $targetAndParameters['controller'];
        // resolve full namespace (including vendor) when we know which controller to access.
        $namespace = $this->resolveNamespace($targetAndParameters['namespace'], $controller);
        $action = $targetAndParameters['action'];
        $parameters = $targetAndParameters['parameters'];

        if ($action === null || $controller === null || $namespace === null) {
            throw new InvalidUriException("Invalid route path, no action, controller, and / or namespace: " . $uri);
        }

        $dispatcher = new Dispatcher($namespace, $controller, $action, $parameters);

        return $this->performRequest($dispatcher);
    }

    /**
     * @param Dispatcher $dispatcher request dispatcher
     * @return Response object
     * @throws ClassNotFoundException
     * @throws MethodNotFoundException
     * @throws ParameterMismatchException
     * @throws ReflectionException when invoke fails
     */
    private function performRequest(Dispatcher $dispatcher): Response
    {
        $session = new Session();
        $request = new Request();
        $response = new Response();

        $dispatcher->dispatch($request, $response, $session);
        return $response;
    }


    /**
     * @param string $path path to extract
     * @param array $default list of routing defaults (controller, acount)
     * @return array containing expected controller action
     */
    private function parsePath(string $path, array $defaults): array
    {
        $empty_filter = function ($value) {
            return $value !== '';
        };
        $pathElements = array_values(array_filter(explode("/", $path), $empty_filter));

        $result = [
            "namespace" => null,
            "controller" => null,
            "action" => null,
            "parameters" => []
        ];
        foreach ($defaults as $key => $val) {
            $result[$key] = $val;
        }

        foreach ($pathElements as $idx => $element) {
            if ($idx == 0) {
                $result["namespace"] = str_replace('_', '', ucwords($element, '_'));
            } elseif ($idx == 1) {
                $result["controller"] = str_replace('_', '', ucwords($element, '_')) . 'Controller';
            } elseif ($idx == 2) {
                $result["action"] = lcfirst(str_replace('_', '', ucwords($element, '_')))  . "Action";
            } else {
                $result["parameters"][] = $element;
            }
        }

        return $result;
    }
}
