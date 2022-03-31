<?php

/**
 * https://github.com/tokenssL/tokenssL-baoTa
 * 禁用错误报告, 防止出现接口响应体异常
 */
error_reporting(0);

//Boot Composer Loader
require __DIR__ . '/vendor/autoload.php';
$pVersion = explode('.', PHP_VERSION);
$realVersion = $pVersion[0] . '.' . $pVersion[1];
# Check PHP Version and Ext Requirements
$requirements = [];
if ($realVersion < "5.6") {
    $requirements[] = [
        'title' => '需要 PHP5.6 版本',
        'desc' => '检测到您正在使用的PHP版本为 ' . $realVersion . ' ，请升级您的默认PHP版本至 PHP >= 5.6，因为运行此客户端需要您系统默认的PHP版本>=5.6, 通常情况下, 宝塔面板首个安装的PHP版本为系统默认版本。'
    ];
}
$extensions = [
    'Sqlite3',
    'OpenSSL',
    'cURL',
    'Mbstring',
];
foreach ($extensions as $extension) {       
    if (!extension_loaded($extension)) {
        $requirements[] = [
            'title' => '需要PHP开启 ' . $extension . ' 扩展',
            'desc' => '请为您的默认PHP版本开启 ' . $extension . ' 扩展, 可以通过编辑您PHP对应的 .ini 配置文件开启。'
        ];
    }
}

$extensions = [
    'xdebug',
    'Xdebug',
];

foreach ($extensions as $extension) {       
    if (extension_loaded($extension)) {
        $requirements[] = [
            'title' => '需要PHP关闭 ' . $extension . ' 扩展',
            'desc' => '因为 ' . $extension . ' 与模版引擎存在冲突，请关闭后使用本插件。'
        ];
    }
}

if (!empty($requirements)) {
    $twig = \TokenSSL\Common\TwigUtils::initTwig();
    die($twig->render('PHPRequirements.html.twig', ['errors' => $requirements]));
}
?>

<?php
//宝塔Linux面板插件demo for PHP
//@author 阿良<287962566@qq.com>

//必需面向对象编程，类名必需为bt_main
//允许面板访问的方法必需是public方法
//通过_get函数获取get参数,通过_post函数获取post参数
//可在public方法中直接return来返回数据到前端，也可以任意地方使用echo输出数据后exit();
//可在./php_version.json中指定兼容的PHP版本，如：["56","71","72","73"]，没有./php_version.json文件时则默认兼容所有PHP版本，面板将选择 已安装的最新版本执行插件
//允许使用模板，请在./templates目录中放入对应方法名的模板，如：test.html，请参考插件开发文档中的【使用模板】章节
//支持直接响应静态文件，请在./static目录中放入静态文件，请参考插件开发文档中的【插件静态文件】章节


class bt_main extends \TokenSSL\Controller\MainController
{
}
