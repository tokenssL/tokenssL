<?php
/**
 * @author: Viskov Sergey
 * @date: 28.04.15
 * @time: 15:58
 */


namespace LTDBeget\apacheConfigurator\interfaces;


interface iDirectivePath
{
    /**
     * @return Array exploded valid path with format ["directive" => $directiveName, "value" => $value, innerDirective => []]
     * where is a name of Apache directive
     * and value its value
     * and innerDirective if directive is a section, here its inner directive with same format
     */
    public function getPath();

    /**
     * get Path object of parent for this directive
     * @return iDirectivePath
     */
    public function getParentPath();

    /**
     * Is this path for root of Apache configuration file
     * @return bool
     */
    public function isRoot();

    /**
     * get Directive type (Apache directive name) of last directive in path
     * @return String
     */
    public function getDirectiveType();

    /**
     * get Directive value (Apache directive value) of last directive in path
     * @return String
     */
    public function getDirectiveValue();

    /**
     * Compare this iDirectivePath with another
     * @param iDirectivePath $directivePath
     * @return boolean true if paths identical
     */
    public function comparePath(iDirectivePath $directivePath);

    /**
     * Make path from this path as context path and type and value as new directive value
     * @param $type
     * @param $value
     * @return iDirectivePath
     */
    public function makeChildDirectivePath($type, $value);
}