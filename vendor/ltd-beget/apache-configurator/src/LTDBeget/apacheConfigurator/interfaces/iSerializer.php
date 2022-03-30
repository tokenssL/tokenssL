<?php
/**
 * @author: Viskov Sergey
 * @date: 28.04.15
 * @time: 15:59
 */


namespace LTDBeget\apacheConfigurator\interfaces;


interface iSerializer
{
    /**
     *
     * @param iConfigurationFile $configurationFile
     * @return array|string
     */
    public static function serialize(iConfigurationFile $configurationFile);

    /**
     * @param String $fileType
     * @param String|array $configuration
     * @return iConfigurationFile
     */
    public static function deserialize($fileType, $configuration);
}