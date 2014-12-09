<?php
/**
 * Created by PhpStorm.
 * User: ad
 * Date: 05-12-14
 * Time: 10:35
 */

namespace Core;


/**
 * Class Singleton
 * @package Core
 */
/**
 * Class Singleton
 * @package Core
 */
abstract class Singleton
{
    /**
     * holder for singleton objects
     * @var static
     */
    private static $instances;

    /**
     * Cannot construct Singleton, use getInstance instead
     */
    final private function __construct()
    {
        // call init on newly created object
        static::init();
    }

    /**
     * Do not clone
     * @throws Exception
     */
    final private function __clone()
    {
        throw new \Exception("An instance of ".get_called_class()." cannot be cloned.");
    }

    /**
     * get (new) instance
     * @return object
     */
    public static function getInstance()
    {
        $className = get_called_class();

        if(isset(self::$instances[$className]) == false) {
            self::$instances[$className] = new static();
        }
        return self::$instances[$className];
    }


    /**
     * by default there must be an inherited init method
     * so an extended class could simply
     * specify its own init
     */
    protected function init(){}

}
