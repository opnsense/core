<?php
namespace OPNsense\Base;

use Phalcon\Mvc\Controller;

class ControllerBase extends Controller
{
    /**
     * Default action. Set the standard layout.
     */
    public function initialize()
    {
        $this->view->setTemplateBefore('default');
    }

}
