<?php
/**
/**
 *    Copyright (C) 2019 Christmann Informationstechnik und Medien
 
 *
 *    ACKNOWLEDGEMENT
 *
 *    This work has been supported by EU H2020 ICT project LEGaTO, contract #780681
 *
 *
 */
 *
 */
namespace OPNsense\LEGaTO\Api;

use \OPNsense\Base\ApiControllerBase;
use \OPNsense\Base\ApiMutableModelControllerBase;
use \OPNsense\Base\UserException;
use \OPNsense\Core\Backend;
use \OPNsense\Core\Config;

/**
 * Class ServiceController
 * @package OPNsense\Cron
 */
class ServiceController extends ApiControllerBase
{
    /**
     * reconfigure LEGaTO
     */
    public function reloadAction()
    {
        $status = "failed";
        if ($this->request->isPost()) {
            $backend = new Backend();
            $bckresult = trim($backend->configdRun('template reload OPNsense/LEGaTO'));
            if ($bckresult == "OK") {
                $status = "ok";
            }
        }
        return array("status" => $status);
    }

    /**
     * test LEGaTO
     */
    public function testAction()
    {
        if ($this->request->isPost()) {
            $backend = new Backend();
            $bckresult = json_decode(trim($backend->configdRun("LEGaTO test")), true);
            if ($bckresult !== null) {
                // only return valid json type responses
                return $bckresult;
            }
        }
        return array("message" => "unable to run config action");
    }
}

?>
