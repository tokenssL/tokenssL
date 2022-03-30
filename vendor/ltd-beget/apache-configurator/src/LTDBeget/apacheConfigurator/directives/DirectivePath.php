<?php
/**
 * @author: Viskov Sergey
 * @date: 28.04.15
 * @time: 17:13
 */


namespace LTDBeget\apacheConfigurator\directives;


use LTDBeget\apacheConfigurator\exceptions\WrongDirectivePathFormat;
use LTDBeget\apacheConfigurator\interfaces\iDirectivePath;

class DirectivePath implements iDirectivePath
{
    /**
     * path with format ["directive" => $directiveName, "value" => $value, innerDirective => []]
     * @var array
     */
    protected $path;

    /**
     *
     * @param $path
     * @throws WrongDirectivePathFormat
     */
    function __construct($path)
    {
        $this->path = $path;
        $this->checkFormat($path);

    }

    /**
     * @return Array exploded valid path with format ["directive" => $directiveName, "value" => $value, innerDirective => []]
     * where is a name of Apache directive
     * and value its value
     * and innerDirective if directive is a section, here its inner directive with same format
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * get Directive type (Apache directive name) of last directive in path
     * @return String
     */
    public function getDirectiveType()
    {
        $path = $this->getPath();
        $directive = !$this->isRoot()?$path["directive"]:"root";

        while(isset($path["innerDirective"]) and is_array($path["innerDirective"]) and count($path["innerDirective"])) {
            $path = $path["innerDirective"];
            $directive = $path["directive"];
        }

        $directive = Directive::reservedWordFlagAdder($directive);

        return $directive;
    }

    /**
     * get Directive value (Apache directive value) of last directive in path
     * @return String
     */
    public function getDirectiveValue()
    {
        $path = $this->getPath();
        $value = !$this->isRoot()?$path["value"]:"";

        while(isset($path["innerDirective"]) and is_array($path["innerDirective"]) and count($path["innerDirective"])) {
            $path = $path["innerDirective"];
            $value = $path["value"];
        }
        return $value;
    }

    /**
     * get Path object of parent for this directive
     * @return iDirectivePath
     */
    public function getParentPath()
    {
        $path = $this->getPath();
        $root = [];

        return new DirectivePath($this->makeParentPath($path, $root));
    }

    /**
     * Make path from this path as context path and type and value as new directive value
     * @param $type
     * @param $value
     * @return DirectivePath
     */
    public function makeChildDirectivePath($type, $value)
    {
        $type = Directive::reservedWordFlagRemover($type);
        $path = $this->getPath();

        if($this->isRoot()) {
            $path = [
                "directive" => $type,
                "value"     => $value
            ];
        } else {
            $inner = &$path;
            while(!$this->isLastDirectiveInPath($inner)) {
                $inner = &$inner["innerDirective"];
            }

            $inner["innerDirective"] = [
                "directive" => $type,
                "value"     => $value
            ];
        }

        return new DirectivePath($path);
    }

    /**
     * @param $path
     * @param $previous
     * @return mixed
     */
    protected function makeParentPath(&$path, &$previous)
    {
        if($this->isLastDirectiveInPath($path)) {
            unset($previous["innerDirective"]);
            return $previous;
        } else {
            $this->makeParentPath($path["innerDirective"], $path);
        }
        return $path;
    }


    /**
     * @param Array $directive
     * @return bool
     */
    protected function isLastDirectiveInPath($directive)
    {
        return !(isset($directive["innerDirective"]) and is_array($directive["innerDirective"]) and count($directive["innerDirective"]));
    }

    /**
     * Is this path for root of Apache configuration file
     * @return bool
     */
    public function isRoot()
    {
        return count($this->path) == 0;
    }

    /**
     * Compare this iDirectivePath with another
     * @param iDirectivePath $directivePath
     * @return boolean true if paths identical
     */
    public function comparePath(iDirectivePath $directivePath)
    {
        return $this->getArrayHash($this->getPath()) == $this->getArrayHash($directivePath->getPath());

    }

    /**
     * Make hash of array for compare
     * @param $array
     * @return string
     */
    protected function getArrayHash($array)
    {
        array_multisort($array);
        return md5(serialize($array));
    }

    /**
     * Checks format of the object path.
     * Right format is ["directive" => $directiveName, "value" => $value, innerDirective => []]
     * where directive is a name of Apache directive
     * and {value} its value
     * @param array $directive
     * @throws WrongDirectivePathFormat
     */
    protected function checkFormat($directive)
    {
        if($directive != []) {
            if(!isset($directive["directive"])) {
                throw new WrongDirectivePathFormat("For all directives in DirectivePath need to define directive name");
            }

            if(!isset($directive["value"])) {
                throw new WrongDirectivePathFormat("For all directives in DirectivePath need to define directive value");
            }

            if(isset($directive["innerDirective"])) {
                if(is_array($directive["innerDirective"])) {
                    $this->checkFormat($directive["innerDirective"]);
                } else {
                    throw new WrongDirectivePathFormat("If isset inner directives, it need to be array");
                }

            }
        }
    }
}