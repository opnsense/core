<?php
require_once("script/load_phalcon.php");

$cpc = new Captiveportal\CPClient();

$acc_list = $cpc->list_accounting();

print_r($acc_list);

//$cpc->portal_allow("test","10.211.55.101","00:1C:42:49:B7:B2","Fritsx");

//$cpc->disconnect("test",array("5489714eba263","gdsajhgadsjhg"));

//$cpc->reconfigure();
//$cpc->refresh_allowed_mac();
//$cpc->refresh_allowed_ips();


//$db = new Captiveportal\DB("test");
//$db->remove_session("XXX");
//$db->insert_session(100,1,"10.211.55.101","00:1C:42:49:B7:B2","frits","XXX","aksjdhaskjh", null,null, null,null, null);
//
//$clients  = $db->listClients( array("sessionid" => "XXX") );
//
//foreach($clients as $client ){
//    print($client->pipeno) ;
//}

//$arp = new \Captiveportal\ARP();
//$arp->setStatic("172.20.0.1",'00:1c:42:49:b7:b1');
//$arp->dropStatic("172.20.0.1");

//$config = \Core\Core\Config::getInstance();

//$config->dump();
//print_r($config->xpath('//pfsense/interfaces/*') );

//$rules= new \Core\Captiveportal\Rules();





