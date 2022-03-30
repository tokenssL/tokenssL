<?php
/**
 * @author: Viskov Sergey
 * @date: 28.04.15
 * @time: 19:33
 */


namespace LTDBeget\apacheConfigurator\interfaces;


interface iContext extends iType
{
    /**
     * return all innerDirectives
     * @return iDirective[]
     */
    public function getInnerDirectives();

    /**
     * iterate throw all children of iContext
     * @yield iDirective
     */
    public function iterateChildren();

    /**
     * add InnerDirective in iContext
     * @param iDirective $directive
     */
    public function appendInnedDirective(iDirective $directive);

    /**
     * detach innerDirective from iContext
     * @param iDirective $directive
     */
    public function detachInnerDirective(iDirective $directive);

    /**
     * is this context root of Apache configuration file
     * @return mixed
     */
    public function isRoot();
}