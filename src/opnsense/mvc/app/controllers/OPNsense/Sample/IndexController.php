<?php

namespace OPNsense\Sample;

class IndexController extends \OPNsense\Base\IndexController
{
    public function indexAction()
    {
        $this->view->title = $this->request->getURI();
        $this->view->pick('OPNsense/Sample/index');
    }

}