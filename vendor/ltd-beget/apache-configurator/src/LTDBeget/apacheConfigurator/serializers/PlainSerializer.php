<?php
/**
 * @author: Viskov Sergey
 * @date: 30.04.15
 * @time: 12:57
 */


namespace LTDBeget\apacheConfigurator\serializers;


use LTDBeget\apacheConfigurator\ConfigurationFile;
use LTDBeget\apacheConfigurator\interfaces\iConfigurationFile;
use LTDBeget\apacheConfigurator\interfaces\iContext;
use LTDBeget\apacheConfigurator\interfaces\iDirective;
use LTDBeget\apacheConfigurator\interfaces\iSerializer;

class PlainSerializer extends BaseSerializer implements iSerializer
{
    /**
     * @return PlainSerializer
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
        $configurationPlain = "";

        foreach($configurationFile->getInnerDirectives() as $directive) {
            $configurationPlain .= self::getInstance()->directiveToPlain($directive);
        }

        return $configurationPlain;
    }

    /**
     * @param String $fileType
     * @param String|array $configuration
     * @return ConfigurationFile
     */
    public static function deserialize($fileType, $configuration)
    {
        $configurationFile = new ConfigurationFile($fileType, []);
        $directivesQueue = self::getInstance()->makeQueueOfDirectives($configuration);
        self::getInstance()->processQueueOfDirectives($directivesQueue, $configurationFile, $configurationFile);

        return $configurationFile;
    }

    /**
     * Make queury of directives, from given string configuration
     * Ignore blank lines and comments lines
     * @param String $configuration
     * @return array
     */
    protected function makeQueueOfDirectives($configuration)
    {
        $queue = explode("\n", $configuration);

        foreach($queue as $key => $vaule) {
            $value = trim($vaule);
            $queue[$key] = $value;

            if($value == "") { // ignore blank
                unset($queue[$key]);
            }
            if(substr($vaule, 0, 1) == "#") { // ignore comments
                unset($queue[$key]);
            }
        }
        return array_values($queue);
    }

    /**
     * Addind directives from query in configurationFile
     * @param Array $directivesArray
     * @param ConfigurationFile $configurationFile
     * @param iContext $context
     */
    protected function processQueueOfDirectives(array &$directivesArray, ConfigurationFile $configurationFile, iContext $context)
    {
        // start process all directives
        while(count($directivesArray)) {
            // get first in stack of directives
            $plain_directive = array_shift($directivesArray);

            //process directive syntx
            // is section?
            if(substr($plain_directive, 0, 1) == "<" and substr($plain_directive, strlen($plain_directive)-1, 1) == ">") {
                $sectionDirective = true;
                // is end of section?
                if(substr($plain_directive, 0, 2) == "</") {
                    $endOfSection = true;
                } else {
                    $endOfSection = false;
                    // cut section delimeters
                    $plain_directive = substr($plain_directive, 1, strlen($plain_directive)-2);
                }
            } else {
                $sectionDirective = false;
                $endOfSection = false;
            }

            if(!$endOfSection) {
                // parse name and value of directive
                $exploded_directive = explode(" ", $plain_directive);
                $directiveName = array_shift($exploded_directive);
                $directiveValue = implode(" ", $exploded_directive);
                $directiveValue = trim($directiveValue);

                // add directive in configurationFile
                $directive = $configurationFile->addDirective(
                    $directiveName,
                    $directiveValue,
                    $context
                );

                // if section, recursively process next directives in queue, while this section wouldn't ends
                if($sectionDirective) {
                    $this->processQueueOfDirectives($directivesArray, $configurationFile, $directive);
                }

            } else {
                // if ends of section stop, this cycle
                break;
            }
        }
        // finaly stop process
        return;
    }



    /**
     * converts directive object to plain with its inner directives as plain too
     * @param iDirective $directive
     * @param int $nestLevel
     * @return array
     */
    protected function directiveToPlain(iDirective $directive, $nestLevel = 0)
    {
        $tabulation     = $nestLevel > 0 ? str_repeat("  ", $nestLevel) : "";

        if ($directive->isSection()) {
            $directiveText = $this->customFilter($directive, "<{$directive->getName()} {$directive->getValue()}>");
            $directivePlain = $tabulation . $directiveText . "\n";
            foreach ($directive->getInnerDirectives() as $innerDirective) {
                $directivePlain .= $this->directiveToPlain($innerDirective, $nestLevel + 1);
            }
            $directivePlain .= $tabulation . "</{$directive->getName()}>\n";
        } else {
            $directiveText = $this->customFilter($directive, "{$directive->getName()} {$directive->getValue()}");
            $directivePlain = $tabulation .$directiveText."\n";
        }

        return $directivePlain;
    }
}