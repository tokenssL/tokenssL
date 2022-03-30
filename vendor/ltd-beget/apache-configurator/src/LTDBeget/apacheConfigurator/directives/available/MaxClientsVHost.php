<?php
/**
 * @author: Viskov Sergey
 * @date: 17.07.15
 * @time: 20:00
 */


namespace LTDBeget\apacheConfigurator\directives\available;


use LTDBeget\apacheConfigurator\directives\Directive;

class MaxClientsVHost extends Directive
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
        return "A separate MaxClients for the vhost. This can be useful if, say, half of your vhosts depend on some NFS server; if the NFS server goes down, you do not want the children waiting forever on NFS to take the non-NFS-dependent hosts down. This can thus act as a safety measure, giving “server too busy” on the NFS-dependent vhosts while keeping the other ones happily running. (Of course, you could use it to simply keep one site from eating way too much resources, but there are probably better ways of doing that.)";
    }

    /**
     * Return Apache directive Syntax
     * @return String
     */
    public static function getSyntax()
    {
        return 'MaxClientsVHost number';
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