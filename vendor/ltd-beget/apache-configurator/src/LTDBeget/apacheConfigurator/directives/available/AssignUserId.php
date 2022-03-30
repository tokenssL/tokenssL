<?php
/**
 * @author: Viskov Sergey
 * @date: 17.07.15
 * @time: 19:57
 */


namespace LTDBeget\apacheConfigurator\directives\available;


use LTDBeget\apacheConfigurator\directives\Directive;

class AssignUserId extends Directive
{
    /**
     * Return link to full description of apache directive
     * @return String
     */
    public static function getApacheDocLink()
    {
        return "http://mpm-itk.sesse.net/";
    }

    /**
     * the source module which defines the directive
     * @return String
     */
    public static function getModule()
    {
        return "mpm_itk_module";
    }

    /**
     * Return text description of apache directive
     * @return String
     */
    public static function getDescription()
    {
        return "Takes two parameters, uid and gid (or really, user name and group name; use “#<uid>” if you want to specify a raw uid); specifies what uid and gid the vhost will run as (after parsing the request etc., of course). Note that if you do not assign a user ID, the default one from Apache will be used.";
    }

    /**
     * Return Apache directive Syntax
     * @return String
     */
    public static function getSyntax()
    {
        return 'AssignUserId #<UID> #<GID>';
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