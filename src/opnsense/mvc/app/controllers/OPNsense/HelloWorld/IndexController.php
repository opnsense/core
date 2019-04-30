<?php
namespace OPNsense\HelloWorld;
class IndexController extends \OPNsense\Base\IndexController
{
	public function indexAction()
	{
		$this->view->pick('OPNsense/HelloWorld/index');
	}
}
?>