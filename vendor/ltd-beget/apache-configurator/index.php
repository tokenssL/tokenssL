<?php
require 'vendor/autoload.php';

use LTDBeget\apacheConfigurator\ConfigurationFile;
use LTDBeget\apacheConfigurator\serializers\ArraySerializer;
use LTDBeget\apacheConfigurator\serializers\JsonSerializer;
use LTDBeget\apacheConfigurator\serializers\PlainSerializer;

$abstractDirectivesTree = [
    [
        "directive" => "directive1",
        "value"     => "asdf",
        "innerDirective" => [
            [
                "directive" => "directive1.1",
                "value"     => "66613666",
                "innerDirective" => [
                    [
                        "directive" => "directive1.1.1",
                        "value"     => "allowForAll"
                    ],
                    [
                        "directive" => "directive1.1.2",
                        "value"     => "allowForAll"
                    ],
                ]
            ],
            [
                "directive" => "directive1.2",
                "value"     => "allowForAll"
            ],
        ]
    ],
    [
        "directive" => "directive2",
        "value"     => "asdf",
        "innerDirective" => [
            [
                "directive" => "directive2.1",
                "value"     => "66613666",
                "innerDirective" => [
                    [
                        "directive" => "directive2.1.1",
                        "value"     => "allowForAll",
                        "innerDirective" => [
                            [
                                "directive" => "directive2.1.1.1",
                                "value"     => "allowForAll"
                            ],
                        ]
                    ],
                    [
                        "directive" => "directive2.1.2",
                        "value"     => "allowForAll"
                    ],
                ]
            ],
            [
                "directive" => "directive2.2",
                "value"     => "allowForAll"
            ],
        ]
    ],
];

$apacheArrayExample = [
    [
        "directive" => "VirtualHost",
        "value"     => "test.foo.bar.com",
        "innerDirective" => [
            [
                "directive" => "Directory",
                "value"     => "/www/public/",
                "innerDirective" => [
                    [
                        "directive" => "AuthDigestQop",
                        "value"     => "auth-int"
                    ],
                    [
                        "directive" => "xml2EncAlias",
                        "value"     => "charset alias"
                    ],
                ]
            ],
            [
                "directive" => "ifMoDuLe",
                "value"     => "mod_macro",
                "innerDirective" => [
                    [
                        "directive" => "UndefMacro",
                        "value"     => "MyName"
                    ],
                ]
            ],
            [
                "directive" => "ReQuiRe",
                "value"     => "mod_macro",
            ]
        ]
    ],
];

    //$configuration = $abstractDirectivesTree;
    $configuration = $apacheArrayExample;

    $configurationFile = ArraySerializer::deserialize(ConfigurationFile::SERVER_CONFIG, $configuration);

    foreach($configurationFile->iterateChildren() as $directive) {
        echo "{$directive->getName()} ".$directive->getValue()."\n";
        echo "Description: ".$directive->getDescription()."\n";
        echo "ApacheDocLink: ".$directive->getApacheDocLink()."\n";
        echo "Syntax: ".$directive->getSyntax()."\n";
    }

//    echo PlainSerializer::serialize($configurationFile);
//    echo ArraySerializer::serialize($configurationFile);
//    echo JsonSerializer::serialize($configurationFile);

