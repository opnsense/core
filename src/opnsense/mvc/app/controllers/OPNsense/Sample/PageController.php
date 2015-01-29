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
     * controller for sample index page, defaults to http://<host>/sample/page
     */
    public function indexAction()
    {
        // load model and send to view, this model is automatically filled with data from the config.xml
        $mdlSample = new Sample();
        $this->view->sample = $mdlSample;

        // set title and pick a template
        $this->view->title = "test page";
        $this->view->pick('OPNsense/Sample/page');
    }

    public function saveAction()
    {
        // save action should be a post, redirect to index
        if ($this->request->isPost() == true) {
            // create model(s)
            $mdlSample = new Sample();

            // Access POST data and save parts to model
            foreach ($this->request->getPost() as $key => $value) {
                $refparts = explode("_", $key);
                if (array_shift($refparts) == "sample") {
                    // this post item belongs to the Sample model (prefixed in view)
                    $mdlSample->setNodeByReference(implode(".", $refparts), $value);
                }
            }
            $mdlSample->serializeToConfig();
            $cnf = Config::getInstance();
            $cnf->save();

            // redirect to index
            $this->dispatcher->forward(array(
                "action" => "index"
            ));

        } else {
            // Forward flow to the index action
            $this->dispatcher->forward(array(
                "action" => "index"
            ));
        }
    }

    public function showAction($postId)
    {
        $sample = new Sample();
        $this->view->title = $sample->title;
        $this->view->items = array(array('field_name' =>'test', 'field_content'=>'1234567','field_type'=>"text") );
        $this->view->data = $sample ;

        // Pass the $postId parameter to the view
        //$this->view->setVar("postId", $postId);
//        $robot = new Sample\Sample();
//        $robot->title = 'hoi';
//
//        $this->view->title = $postId. "/". $this->persistent->name;
//
        $this->view->pick('OPNsense/Sample/page.show');

//        $this->flash->error("You don't have permission to access this area");
//
//        // Forward flow to another action
//        $this->dispatcher->forward(array(
//            "controller" => "sample",
//            "action" => "index"
//        ));
    }
}
