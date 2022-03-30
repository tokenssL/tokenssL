<?php
/**
 * @author: Viskov Sergey
 * @date: 28.04.15
 * @time: 15:58
 */


namespace LTDBeget\apacheConfigurator\interfaces;


interface iDirective extends iContext, iContextAble
{
    /**
     * the source module which defines the directive
     * @return String
     */
    public static function getModule();

    /**
     * Value of Apache directive
     * @return String
     */
    public function getValue();

    /**
     * set value for directive
     * @param String $value
     * @return void
     */
    public function setValue($value);

    /**
     * Return Apache directive Syntax
     * @return String
     */
    public static function getSyntax();

    /**
     * Return text description of apache directive
     * @return String
     */
    public static function getDescription();

    /**
     * Return link to full description of apache directive
     * @return String
     */
    public static function getApacheDocLink();

    /**
     * is this directive can include inner directives
     * @return boolean
     */
    public function isSection();
}