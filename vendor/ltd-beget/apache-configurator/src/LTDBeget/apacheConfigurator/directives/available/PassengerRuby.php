<?php
namespace LTDBeget\apacheConfigurator\directives\available;

use LTDBeget\apacheConfigurator\directives\Directive;

class PassengerRuby extends Directive
{
    /**
     * Return link to full description of apache directive
     *
     * @return String
     */
    public static function getApacheDocLink()
    {
        return 'https://www.phusionpassenger.com/library/config/apache/reference/#passengerruby';
    }

    /**
     * the source module which defines the directive
     *
     * @return String
     */
    public static function getModule()
    {
        return "passenger_module";
    }

    /**
     * Return text description of apache directive
     *
     * @return String
     */
    public static function getDescription()
    {
        return 'The PassengerRuby option specifies the Ruby interpreter to use for serving Ruby web applications.';
    }

    /**
     * Return Apache directive Syntax
     *
     * @return String
     */
    public static function getSyntax()
    {
        return 'PassengerRuby value';
    }

    /**
     * is this directive can include inner directives
     *
     * @return boolean
     */
    public function isSection()
    {
        return false;
    }

}