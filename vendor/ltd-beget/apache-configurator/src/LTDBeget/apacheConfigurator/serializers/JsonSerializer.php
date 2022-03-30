<?php
/**
 * @author: Viskov Sergey
 * @date: 30.04.15
 * @time: 12:56
 */


namespace LTDBeget\apacheConfigurator\serializers;


use LTDBeget\apacheConfigurator\interfaces\iConfigurationFile;
use LTDBeget\apacheConfigurator\interfaces\iSerializer;

class JsonSerializer extends BaseSerializer implements iSerializer
{
    /**
     * @return JsonSerializer
     */
    protected static function getInstance()
    {
        return parent::getInstance();
    }

    /**
     *
     * @param iConfigurationFile $configurationFile
     * @return array|string
     */
    public static function serialize(iConfigurationFile $configurationFile)
    {
        return json_encode(ArraySerializer::serialize($configurationFile));
    }

    /**
     * @param String $fileType
     * @param String $configuration valid json encoded string
     * @return iConfigurationFile
     */
    public static function deserialize($fileType, $configuration)
    {
        return ArraySerializer::deserialize($fileType, json_decode($configuration, true));
    }
}