<?php
/**
 * @author: Viskov Sergey
 * @date: 28.04.15
 * @time: 15:19
 */


namespace LTDBeget\apacheConfigurator;


use LTDBeget\apacheConfigurator\directives\Directive;
use LTDBeget\apacheConfigurator\directives\DirectivePath;
use LTDBeget\apacheConfigurator\directives\Unknown;
use LTDBeget\apacheConfigurator\exceptions\NotAllowedContextException;
use LTDBeget\apacheConfigurator\exceptions\NotAllowedValueException;
use LTDBeget\apacheConfigurator\exceptions\NotFoundDirectiveException;
use LTDBeget\apacheConfigurator\exceptions\NotFoundFileTypeException;
use LTDBeget\apacheConfigurator\exceptions\WrongDirectivePathFormat;
use LTDBeget\apacheConfigurator\interfaces\iConfigurationFile;
use LTDBeget\apacheConfigurator\interfaces\iContext;
use LTDBeget\apacheConfigurator\interfaces\iDirective;
use LTDBeget\apacheConfigurator\interfaces\iDirectivePath;

class ConfigurationFile implements iConfigurationFile
{
    const SERVER_CONFIG = 'serverConfig';
    const HTACCESS      = "htaccess";

    /**
     * type of current configurationFile "serverConfig", "htaccess"
     * @var String
     */
    protected $fileType;

    protected $innerDirectives = [];

    /**
     * list off all Available directives that are declared in library
     * @var Array
     */
    protected $availableList = [];


    public function __construct($fileType)
    {
        if(!in_array($fileType, [ConfigurationFile::SERVER_CONFIG, ConfigurationFile::HTACCESS])) {
            throw new NotFoundFileTypeException("$fileType is not allowed file type for apache config file");
        }

        $this->setAvailableList();

        $this->fileType = $fileType;

    }

    /**
     * set list off all Available directives that are declared in library
     */
    protected function setAvailableList()
    {
        $pathToDirectives = __DIR__.DIRECTORY_SEPARATOR."directives".DIRECTORY_SEPARATOR."available";
        $files = scandir($pathToDirectives);
        $this->availableList = [];
        foreach($files as $file) {
            $directiveName = Directive::reservedWordFlagRemover(pathinfo($file)['filename']);
            $lowerCaseName = strtolower($directiveName);
            if(Directive::isReservedWordDirective($lowerCaseName)) {
                $directiveName = pathinfo($file)['filename'];
            }
            $this->availableList[$lowerCaseName] = $directiveName;
        }
    }

    /**
     * @param String $directiveName name of Apache directive
     * @param String $value value of Apache directive
     * @param iContext $context
     * @return iDirective
     * @throws NotFoundDirectiveException
     * @throws WrongDirectivePathFormat
     * @throws NotAllowedValueException
     */
    public function addDirective($directiveName, $value, iContext $context = null)
    {
        $this->checkOnControlSymbols($directiveName);
        $this->checkOnControlSymbols($value);

        if(is_null($context)) {
            $context = $this;
        }

        $directiveName =  $this->formatDirectiveName($directiveName);

        // if identical directive already exists, return it, without adding it again
        $alreadyExists = $this->getInnerDirectiveIfExists($directiveName, $value, $context);
        if(!is_null($alreadyExists)) {
            return $alreadyExists;
        }

        $className = __NAMESPACE__."\\directives\\available\\".$directiveName;
        if(class_exists($className)) {
            $directive = new $className($value, $context);
        } else {
            $directive = new Unknown($directiveName, $value, false, $context);
        }

        $context->appendInnedDirective($directive);

        return $directive;
    }

    /**
     * @param iDirective $directive
     * @return void
     */
    public function removeDirective(iDirective $directive)
    {
        $context = $directive->getContext();
        $context->detachInnerDirective($directive);
    }

    /**
     * string type of current configurationFile "serverConfig", "htaccess"
     * @return String
     */
    public function getName()
    {
        return $this->fileType;
    }

    /**
     * Full name of ConfigurationFile
     * @return String
     */
    public static function getFullName()
    {
        return __CLASS__;
    }

    /**
     * return all innerDirectives
     * @return iDirective[]
     */
    public function getInnerDirectives()
    {
        return $this->innerDirectives;
    }

    /**
     * is this context root of Apache configuration file
     * @return mixed
     */
    public function isRoot()
    {
        return true;
    }

    /**
     * iterate throw all children of iInnerDirectiveAble
     * @yield iDirective
     * @return iDirective[]
     */
    public function iterateChildren()
    {
        foreach($this->getInnerDirectives() as $directive) {
            foreach($directive->iterateChildren() as $innerDirective) {
                yield $innerDirective;
            }
        }
    }

    /**
     * add InnerDirective in iInnerDirectiveAble
     * @param iDirective $directive
     * @return mixed
     * @throws NotAllowedContextException
     */
    public function appendInnedDirective(iDirective $directive)
    {
        if($this !== $directive->getContext()) {
            throw new NotAllowedContextException("Trying to append to {$this->getName()} directive {$directive->getContext()} which is not its context");
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
     * @param iDirectivePath $directivePath
     * @param bool $throwException
     * @return iDirective|null
     * @throws NotFoundDirectiveException
     */
    public function findByPath(iDirectivePath $directivePath, $throwException = false)
    {
        foreach ($this->iterateChildren() as $directive) {
            if ($directivePath->comparePath($directive->getPath())) {
                return $directive;
            }
        }

        if ($throwException) {
            $path = json_encode($directivePath->getPath());
            throw new NotFoundDirectiveException("Directive by path does not exist: $path");
        }
        return null;
    }

    /**
     * Return path of root file
     * @return iDirectivePath
     */
    public function getPath()
    {
        return new DirectivePath([]);
    }

    /**
     * Find context by path and creates if doesn't exist
     * @param iDirectivePath|null $contextPath
     * @return ConfigurationFile|iDirective
     * @throws NotFoundDirectiveException
     * @throws WrongDirectivePathFormat
     */
    public function getContextByPath(iDirectivePath $contextPath)
    {
        if(is_null($contextPath) or $contextPath->isRoot()) {
            $context = $this;
        } else {
            $context = $this->findByPath($contextPath);
            if(is_null($context)) {
                $context = $this->addDirective(
                    $contextPath->getDirectiveType(),
                    $contextPath->getDirectiveValue(),
                    $this->getContextByPath($contextPath->getParentPath())
                );
            }
        }

        return $context;
    }

    /**
     * Find in context inner directive with identical name and value
     * @param $directiveName
     * @param $value
     * @param iContext $context
     * @return iDirective|null  if exists return it or return null
     */
    protected function getInnerDirectiveIfExists($directiveName, $value, iContext $context)
    {
        foreach($context->getInnerDirectives() as $innerDirective) {
            if($innerDirective->getName() == $directiveName and $innerDirective->getValue() == $value) {
                return $innerDirective;
            }
        }
        return null;
    }

    /**
     * if directive founded in available, return it name as it set there,
     * else return its in lowerCase
     * format directiveName to format that set in
     * @param String $directiveName
     * @return string
     */
    protected function formatDirectiveName($directiveName)
    {
        $formattedDirectiveName = Directive::reservedWordFlagRemover(strtolower($directiveName));

        if(array_key_exists($formattedDirectiveName, $this->availableList)) {
            $directiveName = $this->availableList[$formattedDirectiveName];
        }

        if(in_array($formattedDirectiveName, Directive::$reservedWordDirectives)) {
            $directiveName = Directive::reservedWordFlagRemover($directiveName);
        }

        return $directiveName;
    }

    /**
     * @param $value
     * @throws NotAllowedValueException
     */
    protected function checkOnControlSymbols($value)
    {
        if(!ctype_print($value)) {
            throw new NotAllowedValueException("Control symbols is not allowed");
        }
    }
}
