<?php
/**
 * @author: Viskov Sergey
 * @date: 06.05.15
 * @time: 17:56
 */


namespace LTDBeget\apacheConfigurator\parser;


use DOMDocument;
use DOMElement;

class Parser
{
    /**
     * Site of apache docu,emtation
     * @var string
     */
    protected $site = "http://httpd.apache.org";

    /**
     * Part of path to apache directives documentation
     * @var string
     */
    protected $directive = "/docs/2.4/mod/";

    /**
     * Part of path to list of all apache directives
     * @var string
     */
    protected $fullList = "/docs/2.4/mod/directives.html";



    public function getPathToDirectiveFile($name)
    {
        return realpath(__DIR__.DIRECTORY_SEPARATOR."..").DIRECTORY_SEPARATOR."directives".DIRECTORY_SEPARATOR."available".DIRECTORY_SEPARATOR.$name.".php";
    }

    public function generateDirectiveClasses()
    {
        foreach($this->getDirectivesDocumentation() as $directiveDoc) {
            $isSection = preg_match("/^</", $directiveDoc["Syntax"]);
            $tempName = $isSection?"sectionDirectiveTemp.php":"nonSectionDirectiveTemp.php";
            $tempPath = __DIR__.DIRECTORY_SEPARATOR.$tempName;
            $template = file_get_contents($tempPath);
            $filePath = "";
            foreach($directiveDoc as $name => $value) {

                $value = preg_replace("/(<code class=\".*\">|<\/code>)/", "", $value);
                $value = preg_replace("/(<\/a>|<a>)/", "", $value);
                $value = preg_replace('/ +/', ' ', $value);
                $value = trim($value);

                if($name == "Name") {
                    if(in_array($value, ["Else", "ElseIf", "If", "Include", "Require", "Use"])) {
                        $value = "d".$value;
                    }
                    $filePath = $this->getPathToDirectiveFile($value);
                }

                if($name == "Module") {
                    $value = strip_tags($value);
                }

                if($name == "Description") {
                    $value = strip_tags($value);
                }

                if($name != "Context") {
                    $template = str_replace("{{".mb_strtoupper($name)."}}", $value, $template);
                } else {
                    $contexts = explode(", ", $value);
                    if(!count($contexts)) {
                        throw new \Exception("No context for directive ".json_encode($directiveDoc));
                    }
                    $template = str_replace("{{ALLOWED_CONTEXT}}", $this->makeAllowedContextStr($contexts), $template);
                }
            }

            file_put_contents($filePath, $template);
        }
    }

    protected function makeAllowedContextStr($contexts)
    {
        $contextAsClasses = [];
        foreach($contexts as $context) {
            switch($context) {
                case "server config":
                    $contextAsClasses[] = "ConfigurationFile::SERVER_CONFIG";
                    break;
                case ".htaccess":
                    $contextAsClasses[] = "ConfigurationFile::HTACCESS";
                    break;
                case "virtual host":
                    $contextAsClasses[] = "VirtualHost::getFullName()";
                    break;
                case "directory":
                    $contextAsClasses[] = "Directory::getFullName()";
                    break;
                default:
                    throw new \Exception("No information about context {$context}");
            }
        }

        ob_start();
        foreach($contextAsClasses as $contextClass) {
            echo "
            {$contextClass},";
        }
        $contextStr = ob_get_contents();
        ob_end_clean();

        return $contextStr;
    }

    /**
     * Return array of directive documentation
     * @yield array
     */
    public function getDirectivesDocumentation()
    {
        foreach ($this->getFullList() as $name => $link) {
            if(!class_exists("LTDBeget\\apacheConfigurator\\directives\\available\\".$name)) { // if not Generated already
                echo "start proses directive {$name} link: ".$this->getAbsoluteLink($link)."\n";
                $doc = $this->getDomDocument($this->getAbsoluteLink($link));
                $xpath = new \DOMXPath($doc);
                foreach ($xpath->query("//*[@id='{$name}']") as $tag) {
                    /**
                     * @var DOMElement $tag
                     */
                    $documentation = $tag->parentNode->parentNode;
                    $docTable = $documentation->getElementsByTagName("table");

                    foreach ($docTable as $table) {
                        /**
                         * @var DOMElement $table
                         */
                        if($table->getAttribute("class") != "directive") {
                            continue;
                        }
                        $directiveDoc = [
                            "Name" => $name,
                            "link" => $link
                        ];
                        $parsedTable = $this->parseTable($table->ownerDocument->saveHTML($table));
                        $directiveDoc = array_merge($directiveDoc, $parsedTable);

                        yield $directiveDoc;
                    }
                }
                usleep(400000);
            }
        }
    }

    /**
     * absolute link to list of all apache directives
     * @return string
     */
    protected function getFullListLink()
    {
        return $this->site . $this->fullList;
    }

    /**
     * Path to concrete directive documentation
     * @param $directive
     * @return string
     */
    protected function getDirectiveLink($directive)
    {
        return $this->directive . $directive;
    }

    /**
     * Make absolute path
     * @param $link
     * @return string
     */
    protected function getAbsoluteLink($link)
    {
        return $this->site.$link;
    }

    /**
     * Parse directive documentation table
     * @param $html
     * @return array
     */
    protected function parseTable($html)
    {
        // Find the table
        preg_match("/<table class=\"directive\">.*?<\/[\s]*table>/s", $html, $table_html);

        // Get title for each row
        preg_match_all("/<th.*?>(.*?)<\/[\s]*th>/s", $table_html[0], $matches);
        $row_headers = $matches[1];

        // Iterate each row
        preg_match_all("/<tr.*?>(.*?)<\/[\s]*tr>/s", $table_html[0], $matches);

        $table = [];

        foreach ($matches[1] as $key => $row_html) {
            preg_match_all("/<td.*?>(.*?)<\/[\s]*td>/s", $row_html, $td_matches);

            foreach($td_matches[1] as $row_key => $row) {
                $header = str_replace(":", "", strip_tags(html_entity_decode(str_replace("directive-dict.html#", "" ,$row_headers[$key]))));
                $row = preg_replace("/(<code>|<var>|<\/var>|<\/code>)/", "", $row);
                $td = str_replace("\n", ' ', html_entity_decode($row));
                $table[$header] = $td;
            }
        }
        return $table;
    }

    /**
     * Get full list of all directives
     * @return array
     */
    public function getFullList()
    {
        $list = [];
        $xpath = new \DOMXPath($this->getDomDocument($this->getFullListLink()));
        foreach ($xpath->query("*/div[@id='directive-list']/ul/li/a") as $directive) {
            /**
             * @var DOMElement $directive
             */
            $name = preg_replace("/<\/a>/", "", preg_replace("/<a([^>]+)>/", "", $directive->ownerDocument->saveHTML($directive)));
            $name = html_entity_decode($name);
            $name = preg_replace("/[<>]/", "", $name);
            $list[$name] = $this->getDirectiveLink($directive->getAttribute('href'));
        }
        return $list;
    }

    /**
     * @param $link
     * @return DOMDocument
     */
    protected function getDomDocument($link)
    {
        $previous_value = libxml_use_internal_errors(TRUE);
        $doc = new DOMDocument();
        $doc->strictErrorChecking = FALSE;
        $doc->loadHTMLFile($link);
        libxml_clear_errors();
        libxml_use_internal_errors($previous_value);

        return $doc;
    }
}