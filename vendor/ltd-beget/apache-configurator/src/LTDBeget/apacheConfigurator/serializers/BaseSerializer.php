<?php
/**
 * Created by PhpStorm.
 * @author: Viskov
 * Date: 24.11.14
 * Time: 12:58
 */
namespace LTDBeget\apacheConfigurator\serializers;

use LTDBeget\apacheConfigurator\interfaces\iDirective;

abstract class BaseSerializer
{
    /**
     * singleton pool
     * @var array
     */
    protected static $instances = [];

    protected function __construct(){}

    public function __clone(){}

    public function __wakeup(){}

    /**
     * singleton init and store in $instances
     * @return $this
     */
    protected static function getInstance()
    {
        $class = get_called_class();
        if (!array_key_exists($class, self::$instances)) {
            self::$instances[$class] = new $class();
        }
        return self::$instances[$class];
    }

    /**
     * override this method to add custom data filters for serialize
     * @param iDirective $directive
     * @param mixed $data
     * @return mixed
     */
    protected function customFilter(iDirective $directive, $data)
    {
        return $data;
    }
}

