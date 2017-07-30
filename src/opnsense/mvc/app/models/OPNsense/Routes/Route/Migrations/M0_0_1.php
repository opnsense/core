<?php
namespace OPNsense\Routes\Route\Migrations;
use OPNsense\Base\BaseModelMigration;
class M0_0_1 extends BaseModelMigration
{
    public function run($model)
    {
       $model.save();
    }
}
