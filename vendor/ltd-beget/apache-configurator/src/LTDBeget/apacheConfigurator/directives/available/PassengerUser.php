<?php
/**
 * @author: Viskov Sergey
 * @date: 17.07.15
 * @time: 15:01
 */


namespace LTDBeget\apacheConfigurator\directives\available;


use LTDBeget\apacheConfigurator\directives\Directive;

class PassengerUser  extends Directive
{
    /**
     * Return link to full description of apache directive
     * @return String
     */
    public static function getApacheDocLink()
    {
        return "https://www.phusionpassenger.com/documentation/Users%20guide%20Apache.html#PassengerUser";
    }

    /**
     * the source module which defines the directive
     * @return String
     */
    public static function getModule()
    {
        return "passenger_module";
    }

    /**
     * Return text description of apache directive
     * @return String
     */
    public static function getDescription()
    {
        return "If user switching support is enabled, then Phusion Passenger will by default run the web application as the owner of the file config/environment.rb (for Rails apps) or config.ru (for Rack apps). This option allows you to override that behavior and explicitly set a user to run the web application as, regardless of the ownership of environment.rb/config.ru.";
    }

    /**
     * Return Apache directive Syntax
     * @return String
     */
    public static function getSyntax()
    {
        return 'PassengerUser <username>';
    }

    /**
     * is this directive can include inner directives
     * @return boolean
     */
    public function isSection()
    {
        return false;
    }
}