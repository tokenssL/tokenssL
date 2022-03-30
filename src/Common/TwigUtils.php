<?php

namespace TokenSSL\Common;

use Twig\Environment;
use Twig\Loader\FilesystemLoader;

class TwigUtils
{
    /**
     * 获取Twig对象
     * @return Environment
     */
    public static function initTwig()
    {
        $loader = new FilesystemLoader(__DIR__ . '/../../templates');
        $twig = new Environment($loader, [
            // 'cache'=>__DIR__.'/../../templates_cache',
        ]);
        return $twig;
    }
}
