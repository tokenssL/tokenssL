<?php

/**
 * https://github.com/tokenssL/tokenssL
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

class bt_main extends \TokenSSL\Controller\MainController
{
}
