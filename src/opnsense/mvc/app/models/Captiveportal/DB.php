<?php
/**
 * User: ad
 * Date: 08-12-14
 * Time: 18:11
 */

namespace Captiveportal;

/**
 * Class DB, handles captive portal zone's adminstration
 * @package Captiveportal
 */
class DB {

    /**
     * zone name
     * @var string
     */
    private $zone = null ;

    /**
     * database handle
     * @var SQLite3
     */
    private $handle = null ;

    /**
     * datatypes for captive portal table
     * @var array
     */
    private $captiveportal_types = array(
        "allow_time" => \PDO::PARAM_INT,
        "pipeno_in" => \PDO::PARAM_INT,
        "pipeno_out" => \PDO::PARAM_INT,
        "ip" => \PDO::PARAM_STR,
        "mac" => \PDO::PARAM_STR,
        "username" => \PDO::PARAM_STR,
        "sessionid" => \PDO::PARAM_STR,
        "bpassword" => \PDO::PARAM_STR,
        "session_timeout" => \PDO::PARAM_INT,
        "idle_timeout" => \PDO::PARAM_INT,
        "session_terminate_time" => \PDO::PARAM_INT,
        "interim_interval" => \PDO::PARAM_INT,
        "radiusctx" => \PDO::PARAM_STR);

    /**
     * datatypes for captive portal mac table
     * @var array
     */
    private $captiveportal_mac_types=array(
        "mac" => \PDO::PARAM_STR,
        "ip" => \PDO::PARAM_STR,
        "pipeno_in" => \PDO::PARAM_INT,
        "pipeno_out" => \PDO::PARAM_INT,
        "last_checked" => \PDO::PARAM_INT);

    /**
     * datatypes for captive portal ip table
     * @var array
     */
    private $captiveportal_ip_types=array(
        "ip" => \PDO::PARAM_STR,
        "pipeno_in" => \PDO::PARAM_INT,
        "pipeno_out" => \PDO::PARAM_INT,
        "last_checked" => \PDO::PARAM_INT);

    /**
     * open / create new captive portal database for zone
     * @param $zone zone name
     */
    function __construct($zone)
    {
        $this->zone  = $zone ;
        $this->open();
    }

    /**
     * destruct, close sessions
     */
    function __destruct() {
        if ( $this->handle != null){
            $this->handle->close();
        }
    }

    /**
     * open database, on failure send message tot syslog
     * creates structure needed for this captiveportal zone
     * @return SQLite3
     */
    function open(){
        // open database
        $db_path = \Phalcon\DI\FactoryDefault::getDefault()->get('config')->globals->vardb_path ."/captiveportal".$this->zone.".db" ;
        try {
            $this->handle = new \Phalcon\Db\Adapter\Pdo\Sqlite(array("dbname" => $db_path));

            // create structure on new database
            if (!$this->handle->execute("CREATE TABLE IF NOT EXISTS captiveportal (" .                  # table used for authenticated users
                "allow_time INTEGER, pipeno_in INTEGER, pipeno_out INTEGER, ip TEXT, mac TEXT, username TEXT, " .
                "sessionid TEXT, bpassword TEXT, session_timeout INTEGER, idle_timeout INTEGER, " .
                "session_terminate_time INTEGER, interim_interval INTEGER, radiusctx TEXT); " .
                "CREATE UNIQUE INDEX IF NOT EXISTS idx_active ON captiveportal (sessionid, username); " .
                "CREATE INDEX IF NOT EXISTS user ON captiveportal (username); " .
                "CREATE INDEX IF NOT EXISTS ip ON captiveportal (ip); " .
                "CREATE INDEX IF NOT EXISTS starttime ON captiveportal (allow_time);".
                "CREATE TABLE IF NOT EXISTS captiveportal_mac (" .                                      # table used for static mac's
                "mac TEXT, ip TEXT,pipeno_in INTEGER, pipeno_out INTEGER, last_checked INTEGER );" .
                "CREATE UNIQUE INDEX IF NOT EXISTS idx_mac ON captiveportal_mac (mac) ;".
                "CREATE TABLE IF NOT EXISTS captiveportal_ip (" .                                       # table used for static ip's
                "ip TEXT,pipeno_in INTEGER, pipeno_out INTEGER, last_checked INTEGER );" .
                "CREATE UNIQUE INDEX IF NOT EXISTS idx_ip ON captiveportal_ip (ip) "
            )
            ) {

                $logger = new \Phalcon\Logger\Adapter\Syslog("logportalauth", array(
                    'option' => LOG_PID,
                    'facility' => LOG_LOCAL4
                ));
                $logger->error("Error during table {$this->zone} creation. Error message: {$this->handle->lastErrorMsg()}");
                $this->handle = null ;
            }


        }catch (\Exception $e) {
            $logger = new \Phalcon\Logger\Adapter\Syslog("logportalauth", array(
                'option' => LOG_PID,
                'facility' => LOG_LOCAL4
            ));
            $logger->error("Error opening database for zone " . $this->zone . " : ".$e->getMessage()." ");
            $this->handle = null ;
        }

        return $this->handle;

    }

    /**
     * remove session(s) from database
     * @param $sessionids session ids ( or id )
     */
    function remove_session($sessionids){
        if ( $this->handle != null ){
            if ( is_array($sessionids) ) $tmpids = $sessionids;
            else $tmpids = array($sessionids);

            $this->handle->begin() ;
            $stmt = $this->handle->prepare('DELETE FROM captiveportal WHERE sessionid = :sessionid');
            foreach( $tmpids as $session ) {
                $this->handle->executePrepared($stmt, array('sessionid' => $session),array("sessionid"=>\PDO::PARAM_STR));
                $stmt->execute();
            }
            $this->handle->commit() ;

        }
    }

    /**
     *
     * @param string $sessionid session id
     * @param Array() $content data to alter ( fields from "captiveportal")
     */
    function update_session($sessionid,$content){
        if ( $this->handle != null ) {
            $query = "update captiveportal set ";
            $bind_values = Array("sessionid" => $sessionid);
            foreach ($content as $fieldname => $fieldvalue) {
                // you may not alter data not described in $this->captiveportal_types
                if (array_key_exists($fieldname, $this->captiveportal_types)) {
                    if (sizeof($bind_values) > 1) $query .= " , ";
                    $query .=  $fieldname." = "." :".$fieldname."  ";
                    $bind_values[$fieldname] = $fieldvalue;
                }
            }
            $query .= " where sessionid = :sessionid ";
            try {
                $this->handle->execute($query, $bind_values, $this->captiveportal_types);
            } catch (\Exception $e) {
                $logger = new \Phalcon\Logger\Adapter\Syslog("logportalauth", array(
                    'option' => LOG_PID,
                    'facility' => LOG_LOCAL4
                ));
                $logger->error("Trying to modify DB returned error (zone =  " . $this->zone . " ) : " . $e->getMessage() . " ");
            }
        }
    }

    /**
     * insert new session information into this zone's database
     *
     * @param string $sessionid unique session id
     * @param Array() field content ( defined fields in "captiveportal")
     */
    function insert_session($sessionid,$content){
        if ( $this->handle != null ) {
            // construct insert query, using placeholders for bind variables
            $bind_values = Array("sessionid" => $sessionid);
            $query = "insert into captiveportal (sessionid ";
            $query_values = "values (:sessionid ";
            foreach ($content as $fieldname => $fieldvalue) {
                // you may not alter data not described in $this->captiveportal_types
                if (array_key_exists($fieldname, $this->captiveportal_types)) {
                    $query .= "," . $fieldname . " ";
                    $query_values .= ", :" . $fieldname;
                    $bind_values[$fieldname] = $fieldvalue;
                }
            }
            $query .= " ) " . $query_values . ") ";
            try {
                $this->handle->execute($query, $bind_values, $this->captiveportal_types);
            } catch (\Exception $e) {
                $logger = new \Phalcon\Logger\Adapter\Syslog("logportalauth", array(
                    'option' => LOG_PID,
                    'facility' => LOG_LOCAL4
                ));
                $logger->error("Trying to modify DB returned error (zone =  " . $this->zone . " ) : " . $e->getMessage() . " ");
            }
        }
    }

    /**
     * get captive portal clients
     * @param Array() $args
     */
    function listClients($qryargs,$operator="and",$order_by=null){
        // construct query, only parse fields defined by $this->captiveportal_types
        $qry_tag  = "where " ;
        $query = "select * from captiveportal ";
        $query_order_by = "" ;
        foreach ( $qryargs as $fieldname => $fieldvalue  ){
            if ( array_key_exists($fieldname,$this->captiveportal_types) ){
                $query .= $qry_tag . $fieldname." = "." :".$fieldname."  ";
                $qry_tag = " ".$operator." ";
            }
        }

        // apply ordering to result, validate fields
        if (is_array($order_by)){
            foreach ( $order_by as $fieldname   ){
                if ( is_array($order_by) && in_array($fieldname,$order_by) ) {
                    if ($query_order_by != "") {
                        $query_order_by .= " , ";
                    }
                    $query_order_by .= $fieldname;
                }

            }
        }
        if ( $query_order_by != "" ) $query .= " order by " . $query_order_by;


        $resultset = $this->handle->query($query, $qryargs, $this->captiveportal_types);
        $resultset->setFetchMode(\Phalcon\Db::FETCH_OBJ);

        return $resultset->fetchAll();
    }

    /**
     *
     * @return mixed number of connected users/clients
     */
    function countClients(){
        $query = "select count(*) cnt from captiveportal ";

        $resultset = $this->handle->query($query, array(), $this->captiveportal_types);
        $resultset->setFetchMode(\Phalcon\Db::FETCH_OBJ);

        return $resultset->fetchAll()[0]->cnt;

    }

    /**
     * list all fixed ip addresses for this zone
     *
     * @return Array()
     */
    function listFixedIPs(){
        $result = array();
        if ($this->handle != null ) {
            $resultset = $this->handle->query("select ip,pipeno_in,pipeno_out,last_checked from captiveportal_ip");
            $resultset->setFetchMode(\Phalcon\Db::FETCH_OBJ);

            foreach ($resultset->fetchAll() as $record) {
                $result[$record->ip] = $record;
            }
        }

        return $result;
    }

    /**
     * insert new passthru mac address
     * @param $ip hosts ip address
     */
    function upsertFixedIP($ip,$pipeno_in=null,$pipeno_out=null){
        // perform an upsert to update the data for this physical host.
        // unfortunately this costs an extra write io for the first record, but provides cleaner code
        $params = array("ip"=>$ip,"pipeno_in"=>$pipeno_in,"pipeno_out"=>$pipeno_out,"last_checked"=>time());
        $this->handle->execute("insert or ignore into captiveportal_ip(ip) values (:ip)", array("ip"=>$ip),$this->captiveportal_ip_types);
        $this->handle->execute("update captiveportal_ip set ip=:ip, last_checked=:last_checked, pipeno_in = :pipeno_in, pipeno_out = :pipeno_out where ip =:ip ", $params,$this->captiveportal_ip_types);
    }

    /**
     * drop address from administration (captiveportal_ip)
     * @param $mac physical address
     */
    function dropFixedIP($ip){
        $this->handle->execute("delete from  captiveportal_ip where ip =:ip ", array("ip"=>$ip),$this->captiveportal_ip_types);
    }

    /**
     * list all passthru mac addresses for this zone
     *
     * @return Array()
     */
    function listPassthruMacs(){
        $result = array();
        if ($this->handle != null ) {
            $resultset = $this->handle->query("select mac,ip,last_checked,pipeno_in,pipeno_out from captiveportal_mac");
            $resultset->setFetchMode(\Phalcon\Db::FETCH_OBJ);

            foreach ($resultset->fetchAll() as $record) {
                $result[$record->mac] = $record;
            }
        }

        return $result;
    }

    /**
     * insert new passthru mac address
     * @param $mac physical address
     * @param $ip hosts ip address
     */
    function upsertPassthruMAC($mac,$ip,$pipeno_in=null,$pipeno_out=null){
        // perform an upsert to update the data for this physical host.
        // unfortunately this costs an extra write io for the first record, but provides cleaner code
        $params = array("mac"=>$mac,"ip"=>$ip,"pipeno_in"=>$pipeno_in,"pipeno_out"=>$pipeno_out,"last_checked"=>time());
        $this->handle->execute("insert or ignore into captiveportal_mac(mac) values (:mac)", array("mac"=>$mac),$this->captiveportal_mac_types);
        $this->handle->execute("update captiveportal_mac set ip=:ip, last_checked=:last_checked, pipeno_in = :pipeno_in, pipeno_out = :pipeno_out where mac =:mac ", $params,$this->captiveportal_mac_types);
    }

    /**
     * drop address from administration (captiveportal_mac)
     * @param $mac physical address
     */
    function dropPassthruMAC($mac){
        $this->handle->execute("delete from  captiveportal_mac where mac =:mac ", array("mac"=>$mac),$this->captiveportal_mac_types);
    }




}