<?php
namespace LTDBeget\apacheConfigurator\directives\available;

use LTDBeget\apacheConfigurator\directives\Directive;

class PassengerEnabled extends Directive
{
    /**
     * Return link to full description of apache directive
     *
     * @return String
     */
    public static function getApacheDocLink()
    {
        return 'https://www.phusionpassenger.com/library/config/apache/reference/#passengerenabled';
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
        return 'This option enables or disables Passenger for that particular context';
    }

    /**
     * Return Apache directive Syntax
     *
     * @return String
     */
    public static function getSyntax()
    {
        return 'PassengerEnabled on|off';
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