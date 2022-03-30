<?php
/**
 * @author: Viskov Sergey
 * @date: 30.04.15
 * @time: 12:57
 */


namespace LTDBeget\apacheConfigurator\serializers;


use LTDBeget\apacheConfigurator\ConfigurationFile;
use LTDBeget\apacheConfigurator\directives\DirectivePath;
use LTDBeget\apacheConfigurator\interfaces\iConfigurationFile;
use LTDBeget\apacheConfigurator\interfaces\iDirective;
use LTDBeget\apacheConfigurator\interfaces\iSerializer;

class ArraySerializer extends BaseSerializer implements iSerializer
{
    /**
     * @return ArraySerializer
     */
    protected static function getInstance()
    {
        return parent::getInstance();
    }

    /**
     * @param iConfigurationFile $configurationFile
     * @return array|string
     */
    public static function serialize(iConfigurationFile $configurationFile)
    {
        $configurationArray = [];

        foreach($configurationFile->getInnerDirectives() as $directive) {
            $configurationArray[] = self::getInstance()->directiveToArray($directive);
        }

        return $configurationArray;
    }

    /**
     * @param String $fileType
     * @param Array $configuration
     * @return ConfigurationFile
     */
    public static function deserialize($fileType, $configuration)
    {
        $configurationFile = new ConfigurationFile($fileType);

        $leafs = self::getInstance()->explodeOnLeafs($configuration);
        $paths = self::getInstance()->explodeOnPath($leafs);


        foreach($paths as $key => $path) {
            $fullPath = new DirectivePath($path);
            $parentPath = $fullPath->getParentPath();
            $context = $configurationFile->getContextByPath($parentPath);
            $configurationFile->addDirective($fullPath->getDirectiveType(), $fullPath->getDirectiveValue(), $context);
        }

        return $configurationFile;
    }

    /**
     * @param array $paths
     * @return array
     */
    protected function explodeOnPath($paths)
    {
        $formatPaths = [];
        foreach($paths as $path) {
            unset($innerDirective);
            do {
                if(!isset($innerDirective)) {
                    $innerDirective = [
                        "directive"      => $path["directive"],
                        "value"          => $path["value"]
                    ];
                }

                if(isset($path["parent"])) {
                    $innerDirective = [
                        "directive"      => $path["parent"]["directive"],
                        "value"          => $path["parent"]["value"],
                        "innerDirective" => $innerDirective
                    ];
                    $path = $path["parent"];
                }

            } while(isset($path["parent"]));
            $formatPaths[] = $innerDirective;
        }

        return $formatPaths;
    }

    protected function explodeOnLeafs($directives, $parentKey = null, &$paths = [])
    {
        foreach($directives as $key => $directive) {
            $childKey = is_null($parentKey)?$key:$parentKey.$key;
            $path = [
                "directive"      => $directive["directive"],
                "value"          => $directive["value"]
            ];
            if(!is_null($parentKey)) {
                $path["parent"] = $paths[$parentKey];
            }
            $paths[$childKey] = $path;
            if(isset($directive["innerDirective"])) {
                self::explodeOnLeafs($directive["innerDirective"], $childKey, $paths);
            }
        }

        return $paths;

    }

    /**
     * converts directive object to array with its inner directives as array too
     * @param iDirective $directive
     * @return array
     */
    protected function directiveToArray(iDirective $directive)
    {
        $directiveArray = $this->customFilter($directive, [
            "directive" => $directive->getName(),
            "value"     => $directive->getValue()
        ]);

        if($directive->isSection()) {
            $directiveArray["innerDirective"] = [];
            foreach($directive->getInnerDirectives() as $innerDirective) {
                $directiveArray["innerDirective"][] = self::getInstance()->directiveToArray($innerDirective);
            }
        }

        return $directiveArray;
    }
}