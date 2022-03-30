<?php
/**
 * @author: Viskov Sergey
 * @date: 28.04.15
 * @time: 17:09
 */


namespace LTDBeget\apacheConfigurator\directives;


use LTDBeget\apacheConfigurator\exceptions\NotAllowedContextException;
use LTDBeget\apacheConfigurator\exceptions\NotAllowedValueException;
use LTDBeget\apacheConfigurator\interfaces\iDirective;
use LTDBeget\apacheConfigurator\interfaces\iDirectivePath;
use LTDBeget\apacheConfigurator\interfaces\iContext;
use LTDBeget\apacheConfigurator\serializers\PlainSerializer;

class Directive implements iDirective
{

    /**
     * Names of directives, which names are reserved words
     * @var array
     */
    static public $reservedWordDirectivesFlagged = ["delse", "delseif", "dif", "dinclude", "drequire", "duse"];
    static public $reservedWordDirectives = ["else", "elseif", "if", "include", "require", "use"];

    /**
     * Site of Apache full documentation
     * @var string
     */
    protected static $apacheSite = "http://httpd.apache.org";

    /**
     * @var Array|null
     */
    protected $allowedContext;

    /**
     * @var iContext
     */
    protected $context;

    /**
     * @var Array|null
     */
    protected $allowedValues = null;

    /**
     * @var String
     */
    protected $value;

    /**
     * @var iDirective[]
     */
    protected $innerDirectives = null;


    /**
     * @param String $value
     * @param iContext $context
     */
    public function __construct($value, iContext $context)
    {
        $this->setValue($value);
        $this->setContext($context);
    }

    /**
     * the source module which defines the directive
     * @return String
     */
    public static function getModule()
    {
        return "abstract directive";
    }

    /**
     * Name of Apache directive
     * @return String
     */
    public function getName()
    {
        $classNameWithNamespace = get_class($this);
        $type = substr($classNameWithNamespace, strrpos($classNameWithNamespace, '\\')+1);
        $type = self::reservedWordFlagRemover($type);
        return $type;
    }

    /**
     * is this context root of Apache configuration file
     * @return mixed
     */
    public function isRoot()
    {
        return false;
    }

    /**
     * Delete flag that indicates type(name) of directive that has name with reserved words
     * @param $type
     * @return string
     */
    public static function reservedWordFlagRemover($type)
    {
        if(in_array(strtolower($type), Directive::$reservedWordDirectivesFlagged)) {
            $type = substr($type, 1, strlen($type)-1);
        }
        return $type;
    }

    /**
     * is directive name same as php reserved words
     * @param $type
     * @return bool
     */
    public static function isReservedWordDirective($type)
    {
        return in_array(strtolower($type), Directive::$reservedWordDirectives);
    }

    /**
     * Add flag that indicates type(name) of directive that has name with reserved words
     * @param $type
     * @return string
     */
    public static function reservedWordFlagAdder($type)
    {
        if(self::isReservedWordDirective($type)) {
            $type = "d".$type;
        }
        return $type;
    }

    /**
     * Name of Apache directive with full qualified namespace
     * @return String
     */
    public static function getFullName()
    {
        return get_called_class();
    }

    /**
     * Name of Apache directive avalible without creation directive obj
     */
    public static function directiveName()
    {
        $classNameWithNamespace = self::getFullName();
        $type = substr($classNameWithNamespace, strrpos($classNameWithNamespace, '\\')+1);
        $type = self::reservedWordFlagRemover($type);
        return $type;
    }

    /**
     * Value of Apache directive
     * @return String
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * @param String $value
     * @return void
     * @throws NotAllowedValueException
     */
    public function setValue($value)
    {
        if($this->isAllowedValue($value)) {
            $this->value = $value;
        } else {
            throw new NotAllowedValueException("Its now allowed to set {$value} in {$this->getName()} directive. Example syntax is {$this->getSyntax()}");
        }
    }

    /**
     * Return Apache directive Syntax
     * @return String
     */
    public static function getSyntax()
    {
        return "abstract directive";
    }

    /**
     * Return text description of apache directive
     * @return String
     */
    public static function getDescription()
    {
        return "abstract directive";
    }

    /**
     * Return link to full description of apache directive
     * @return String
     */
    public static function getApacheDocLink()
    {
        return Directive::$apacheSite."/docs/2.4/mod/directives.html";
    }

    /**
     * Current context of directive or root of file
     * @return iContext
     */
    public function getContext()
    {
        return $this->context;
    }

    /**
     * @param iContext $context
     * @throws NotAllowedContextException
     */
    public function setContext(iContext $context)
    {
        if($this->isAllowedContext($context)) {
            $this->context = $context;
        } else {
            throw new NotAllowedContextException("Its now allowed to set {$this->getName()} in {$context->getName()}");
        }
    }

    /**
     * return all innerDirectives
     * @return iDirective[]
     */
    public function getInnerDirectives()
    {
        return $this->isSection()?$this->innerDirectives:null;
    }

    /**
     * is this directive can include inner directives
     * @return boolean
     */
    public function isSection()
    {
        return false;
    }

    /**
     * add InnerDirective in iContext
     * @param iDirective $directive
     * @throws NotAllowedContextException
     */
    public function appendInnedDirective(iDirective $directive)
    {
        if(!$this->isSection()) {
            throw new NotAllowedContextException("You trying add directive as inner in non section directive");
        }

        if($directive->getContext() !== $this) {
            throw new NotAllowedContextException("trying add inner directive in {$this->getName()} with context of another directive");
        }
        $this->innerDirectives[] = $directive;
    }

    /**
     * detach innerDirective from iInnerDirectiveAble
     * @param iDirective $directive
     * @throws NotAllowedContextException
     */
    public function detachInnerDirective(iDirective $directive)
    {
        if($this !== $directive->getContext()) {
            throw new NotAllowedContextException("Trying to detach from {$this->getName()} directive {$directive->getContext()} which is not its context");
        }

        foreach($this->innerDirectives as $key => $innerDirective) {
            if($directive === $innerDirective) {
                unset($this->innerDirectives[$key]);
            }
        }
    }

    /**
     * Iterate from iContextAble through it parents to the root
     * @yield iContext
     */
    public function iterateParent()
    {
        $context = $this;
        while(!$context->isRoot()) {
            $context = $context->getContext();
            yield $context;
        }
    }

    /**
     * iterate throw all children of iDirective
     * @yield iDirective
     * @return iDirective[]
     */
    public function iterateChildren()
    {
        yield $this;
        if($this->isSection()) {
            foreach($this->getInnerDirectives() as $directive) {
                foreach($directive->iterateChildren() as $innerDirective) {
                    yield $innerDirective;
                }
            }
        }
    }

    /**
     * Return object of absolute path to this iContextAble directive
     * @return iDirectivePath
     */
    public function getPath()
    {

        $path = [
            "directive" => $this->getName(),
            "value"     => $this->getValue()
        ];
        $finalPath = [];
        foreach($this->iterateParent() as $directive) {
            if($directive instanceof Directive) {
                $finalPath = [
                    "directive"      => $directive->getName(),
                    "value"          => $directive->getValue(),
                    "innerDirective" => $path,
                ];
                $path = $finalPath;
            }
        }
        $finalPath = $finalPath == []?$path:$finalPath;

        return new DirectivePath($finalPath);
    }

    /**
     * Check current directive context on allowed (in what context this directive can be placed)
     * @param iContext $context
     * @return bool
     */
    protected function isAllowedContext(iContext $context)
    {
        if($context->getFullName() == Unknown::getFullName()) {
            return true;
        }

        return is_null($this->allowedContext)?true:in_array($context->getName(), $this->allowedContext);
    }

    /**
     * Set allowed context of concrete directive
     * override in inheritor if need
     */
    protected function setAllowedContext()
    {
        $this->allowedContext = null;
    }

    /**
     * Check current value of directive, on its syntax, if syntax rules defined
     * @param $value
     * @return bool
     */
    protected function isAllowedValue($value)
    {
        if(is_null($this->allowedValues)) {
            return true;
        } else {
            return in_array($value, $this->allowedValues);
        }

    }
}