<?php
/**
 * @author: Viskov Sergey
 * @date: 28.04.15
 * @time: 17:18
 */


namespace LTDBeget\apacheConfigurator\directives;


use LTDBeget\apacheConfigurator\exceptions\NotAllowedContextException;
use LTDBeget\apacheConfigurator\interfaces\iDirective;
use LTDBeget\apacheConfigurator\interfaces\iContext;

class Unknown extends Directive
{
    /**
     * @var String
     */
    protected $module = "unknown";

    /**
     * Text description of apache directive
     * @var String
     */
    protected $description = "unknown";

    /**
     * Example Apache directive syntax
     * @var String
     */
    protected $syntax = 'unknown';

    /**
     * Name of unknown directive as it is
     * @var String
     */
    protected $type;

    protected $isSection = false;

    protected $innerDirectives = [];

    /**
     * @param String $type
     * @param String $value
     * @param Boolean $isSection
     * @param iContext $context
     * @throws \LTDBeget\apacheConfigurator\exceptions\NotAllowedContextException
     * @throws \LTDBeget\apacheConfigurator\exceptions\NotAllowedValueException
     */
    public function __construct($type, $value, $isSection, iContext $context)
    {
        $this->type = $type;
        $this->isSection = $isSection;

        parent::__construct($value,$context);
    }

    /**
     * Name of Apache directive
     * @return String
     */
    public function getName()
    {
        return $this->type;
    }

    /**
     * the source module which defines the directive
     * @return String
     */
    public static function getModule()
    {
        return 'unknown';
    }

    /**
     * Return text description of apache directive
     * @return String
     */
    public static function getDescription()
    {
        return 'unknown';
    }

    /**
     * Return Apache directive Syntax
     * @return String
     */
    public static function getSyntax()
    {
        return 'unknown';
    }

    /**
     * is this directive can include inner directives
     * @return boolean
     */
    public function isSection()
    {
        return count($this->getInnerDirectives()) > 0;
    }

    /**
     * add InnerDirective in iInnerDirectiveAble
     * @param iDirective $directive
     * @throws NotAllowedContextException
     */
    public function appendInnedDirective(iDirective $directive)
    {
        if($directive->getContext() !== $this) {
            throw new NotAllowedContextException("trying add inner directive in {$this->getName()} with context of another directive");
        }
        $this->innerDirectives[] = $directive;
    }

    /**
     * return all innerDirectives
     * @return iDirective[]
     */
    public function getInnerDirectives()
    {
        return $this->innerDirectives;
    }

}