<?php

namespace OPNsense\Routes\Migrations;

use OPNsense\Base\BaseModelMigration;
use OPNsense\Core\Config;

class M1_0_1 extends BaseModelMigration
{
    public function run($model)
    {
        $cfgObj = Config::getInstance()->object();
        if (isset($cfgObj->staticroutes->route)) {
            $modelRoutes = iterator_to_array($model->route->iterateItems());
            foreach ($cfgObj->staticroutes->route as $route) {
                $uuid = (string)$route['uuid'];
                if (!empty($uuid) && isset($modelRoutes[$uuid]) && isset($route->disabled)) {
                    $modelRoutes[$uuid]->enabled = ((int)$route->disabled === 1) ? 0 : 1;
                }
            }
        }
    }
}



