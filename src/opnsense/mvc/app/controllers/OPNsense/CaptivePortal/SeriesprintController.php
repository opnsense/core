<?php
/**
 *    Copyright (C) 2015 Deciso B.V.
 *    Copyright (C) 2015 Fabian Franz
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
namespace OPNsense\CaptivePortal;
use Phalcon\Mvc\View;

/**
 * Class IndexController
 * @package OPNsense\CaptivePortal
 */
class SeriesprintController extends \OPNsense\Base\IndexController
{
    public function seriesprintAction()
    {
        // Do not load the layouts because the layout should not be printed
        $this->view->disableLevel(array(View::LEVEL_LAYOUT => true, View::LEVEL_MAIN_LAYOUT => true, View::LEVEL_BEFORE_TEMPLATE => true, View::LEVEL_AFTER_TEMPLATE => 1));
        $this->view->pick('OPNsense/CaptivePortal/seriesprint');
        // Set the vars
        $this->view->title = gettext("Print Voucher Series");
        $params = $this->dispatcher->getParams();
        if (count($params) == 2 )
        {
          $this->view->zone = $params[0];
          $this->view->roll_number = $params[1];
        }
        else
        {
          $this->view->zone = '';
          $this->view->roll_number = 0;
        }
    }
}
