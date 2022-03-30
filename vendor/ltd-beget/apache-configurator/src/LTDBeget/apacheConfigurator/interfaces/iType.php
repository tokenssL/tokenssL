<?php
/**
 * @author: Viskov Sergey
 * @date: 28.04.15
 * @time: 20:49
 */


namespace LTDBeget\apacheConfigurator\interfaces;


interface iType {
    /**
     * Name of Apache directive or file type
     * @return String
     */
    public function getName();

    /**
     * Name of Apache directive with full qualified namespace or file type
     * @return String
     */
    public static function getFullName();
}