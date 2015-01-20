<?php
namespace OPNsense\Sample;

use Phalcon\Mvc\Controller;
use \OPNsense\Base\ControllerBase;

class PageController extends ControllerBase
{
    public function indexAction()
    {
        $this->view->title = "XXX";
        $this->view->pick('OPNsense/Sample/page');
    }

    public function showAction($postId)
    {
        $sample = new Sample();
        $this->view->title = $sample->title;
        $this->view->items = array(array('field_name' =>'test', 'field_content'=>'1234567','field_type'=>"text") );

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
