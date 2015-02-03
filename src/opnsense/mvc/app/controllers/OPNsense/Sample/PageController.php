<?php

/**
 *    Copyright (C) 2015 Deciso B.V.
 *
 *    All rights reserved.
 *
 *    Redistribution and use in source and binary forms, with or without
 *    modification, are permitted provided that the following conditions are met:
 *
 *    1. Redistributions of source code must retain the above copyright notice,
 *       this list of conditions and the following disclaimer.
 *
 *    2. Redistributions in binary form must reproduce the above copyright
 *       notice, this list of conditions and the following disclaimer in the
 *       documentation and/or other materials provided with the distribution.
 *
 *    THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 *    INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 *    AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 *    AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 *    OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 *    SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 *    INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 *    CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *    ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 *    POSSIBILITY OF SUCH DAMAGE.
 *
 */

namespace OPNsense\Sample;

use Phalcon\Mvc\Controller;
use \OPNsense\Base\ControllerBase;
use \OPNsense\Core\Config;

/**
 * Class PageController
 * @package OPNsense\Sample
 */
class PageController extends ControllerBase
{
    /**
     * update model with data from our request
     * @param BaseModel &$mdlSample model to update
     */
    private function updateModelWithPost(&$mdlSample)
    {
        // Access POST data and save parts to model
        foreach ($this->request->getPost() as $key => $value) {
            $refparts = explode("_", $key);
            if (array_shift($refparts) == "sample") {
                // this post item belongs to the Sample model (see prefix in view)
                $node_found = $mdlSample->setNodeByReference(implode(".", $refparts), $value);
                // new node in the post which is not on disc, create a new child node
                // we need to create new nodes in memory for Array types
                if ($node_found == false && strpos($key, 'childnodes_section_') !== false) {
                    // because all the array items are numbered in order, we know that any item not found
                    // must be a new one.
                    $mdlSample->childnodes->section->add();
                    $mdlSample->setNodeByReference(implode(".", $refparts), $value);
                }
            }
        }
    }

    /**
     * controller for sample index page, defaults to http://<host>/sample/page
     * @param array $error_msg error messages
     */
    public function indexAction($error_msg = array())
    {
        // load model and send to view, this model is automatically filled with data from the config.xml
        $mdlSample = new Sample();
        $this->view->sample = $mdlSample;

        // got forwarded with errors, load form data from post and update on our model
        if ($this->request->isPost() == true && count($error_msg) >0) {
            $this->updateModelWithPost($mdlSample);
        }

        // send error messages to view
        $this->view->error_messages = $error_msg;

        // set title and pick a template
        $this->view->title = "test page";
        $this->view->pick('OPNsense/Sample/page');
    }

    /**
     * Example save action
     * @throws \Phalcon\Validation\Exception
     */
    public function saveAction()
    {
        // save action should be a post, redirect to index
        if ($this->request->isPost() == true) {
            // create model(s)
            $mdlSample = new Sample();

            // update model with request data
            $this->updateModelWithPost($mdlSample);

            if ($this->request->getPost("form_action") == "add") {
                // implement addRow, append new model row and serialize to config
                $mdlSample->childnodes->section->add();
                $mdlSample->serializeToConfig();
            } elseif ($this->request->getPost("form_action") == "save") {
                // implement save, possible removing
                if ($this->request->hasPost("delete")) {
                    // delete selected Rows, cannot combine with add because of the index numbering
                    foreach ($this->request->getPost("delete") as $node_ref => $option_key) {
                        $refparts = explode(".", $node_ref);
                        $delete_key = array_pop($refparts);
                        $parentNode = $mdlSample->getNodeByReference(implode(".", $refparts));
                        if ($parentNode != null) {
                            $parentNode->del($delete_key);
                        }

                    }
                }

                // save data to config
                $validationOutput = $mdlSample->performValidation();
                if ($validationOutput->count() > 0) {
                    // forward to index including errors
                    $error_msgs = array();
                    foreach ($validationOutput as $msg) {
                        $error_msgs[] = array("field" => $msg-> getField(), "msg" => $msg->getMessage());
                    }

                    // redirect to index
                    $this->dispatcher->forward(array(
                        "action" => "index",
                        "params" => array($error_msgs)
                    ));
                    return false;
                }

                $mdlSample->serializeToConfig();
                $cnf = Config::getInstance();
                $cnf->save();
            }
        }

        // redirect to index
        $this->dispatcher->forward(array(
            "action" => "index"
        ));

        return true;
    }
}
