<?php
namespace OPNsense\Core;

class IndexController extends \OPNsense\Base\IndexController
{
    public function indexAction()
    {
        $this->view->pick('OPNsense/Core/index');
    }

}

