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

use Phalcon\Di\FactoryDefault;
use Phalcon\Mvc\View;
use Phalcon\Mvc\View\Engine\Volt as VoltEngine;
use OPNsense\Core\AppConfig;

abstract class Controller
{
    public Session $session;
    public Request $request;
    public Response $response;
    public View $view;
    public Security $security;

    /**
     * Construct a view to render Volt templates, eventually this should be moved to its own controller
     * implementation to avoid API calls constructing components it doesn't need.
     */
    public function __construct()
    {
        $appcfg = new AppConfig();
        $this->view = new View();
        $viewDirs = [];
        foreach ((array)$appcfg->application->viewsDir as $viewDir) {
            $viewDirs[] = $viewDir;
        }
        $this->view->setViewsDir($viewDirs);
        $this->view->setDI(new FactoryDefault());
        $this->view->registerEngines([
            '.volt' => function ($view) use ($appcfg) {
                $volt = new VoltEngine($view);
                $volt->setOptions([
                    'path' => $appcfg->application->cacheDir,
                    'separator' => '_'
                ]);
                $volt->getCompiler()->addFunction('theme_file_or_default', 'view_fetch_themed_filename');
                $volt->getCompiler()->addFunction('file_exists', 'view_file_exists');
                $volt->getCompiler()->addFunction('cache_safe', 'view_cache_safe');
                $volt->getCompiler()->addFilter('safe', 'view_html_safe');
                return $volt;
            }]);
    }

    /**
     * @param Dispatcher $dispatcher
     * @return void
     */
    public function afterExecuteRoute(Dispatcher $dispatcher)
    {
        $this->view->start();
        $this->view->processRender('', '');
        $this->view->finish();

        $this->response->setContent($this->view->getContent());
    }

    public function initialize()
    {
    }
}
