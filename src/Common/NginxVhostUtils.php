<?php

namespace TokenSSL\Common;

use TokenSSL\TokenSSLException;

class NginxVhostUtils
{
    /**
     * 获取宝塔面板的安装路径
     * @return array|false|string
     */
    public static function getBtPanelPath()
    {
        if (is_dir("/www/server/panel")) {
            return "/www/server/panel";
        } elseif (is_dir(getenv("BT_PANEL"))) {
            return getenv("BT_PANEL");
        } else {
            return "/www/server/panel";
        }
    }

    /**
     * 系统默认的配置文件路径, 这里默认选择Nginx目录
     * @return string
     */
    public static function getConfigPath()
    {
        // 检查Webserver类型，生成 configPath
        $db = DatabaseUtils::initBaoTaSystemDatabase();
        $ch = $db->query("select `webserver` from config where id =1")->fetch();
        $webserver = $ch['webserver'];
        if (getenv('BT_PANEL') != "") { # Windows系统
            if (self::getWebserver() === "nginx") {
                return self::getBtPanelPath() . "/../nginx/conf/vhost/";
            }
            if (self::getWebserver() === "apache") {
                return self::getBtPanelPath() . "/../apache/conf/vhost/";
            }
        }
        return realpath(self::getBtPanelPath() . "/vhost/$webserver/") . "/";
    }

    /**
     * 获取 WebServer 类型
     * @return mixed
     */
    public static function getWebserver()
    {
        $db = DatabaseUtils::initBaoTaSystemDatabase();
        $ch = $db->query("select `webserver` from config where id =1")->fetch();
        return $ch['webserver'];
    }

    /**
     * 通过配置文件查找网站运行目录
     * @param $configFile
     * @return string
     * @throws TokenSSLException
     */
    public static function getVhostRunPath($configFile)
    {
        $lines = file($configFile);
        $runPath = null;
        foreach ($lines as $key => $line) {
            # 检查获取配置文件中实际的 RUN PATH，访问路径
            $webserverType = self::getWebserver();
            switch ($webserverType) {
                case 'nginx':
                    $webServerPathKey = 'root';
                    break;
                case 'apache':
                    $webServerPathKey = 'DocumentRoot';
                    break;
                case 'openlitespeed':
                    $webServerPathKey = 'vhRoot';
                    break;
                default:
                    $webServerPathKey = 'root';
            }
            if (strpos($line, $webServerPathKey) !== FALSE) {
                if ($webserverType === "nginx") {
                    preg_match("/\ [a-zA-Z]?[:]?\/(.+);/i", $line, $matches);
                    $runPath = trim("/" . end($matches));
                } elseif ($webserverType === "apache") {
                    preg_match("/DocumentRoot\ \"(.+)\"/i", $line, $matches);
                    $runPath = trim(end($matches));
                } elseif ($webserverType === "openlitespeed") {
                    preg_match("/vhRoot\ (.*)/i", $line, $matches);
                    $runPath = trim(end($matches));
                } else {
                    throw new TokenSSLException("获取站点信息失败, Web服务器配置异常");
                }
            }
        }
        return $runPath;
    }

    /**
     * 获取IIS站点运行目录
     * @param $siteName
     * @return string
     */
    public static function getrealIISSitePath($siteName)
    {
        # 网站主目录
        $db = DatabaseUtils::initBaoTaSystemDatabase();
        $ch = $db->query("select `path` from sites where name = ?", $siteName)->fetch();
        $root = $ch->path;
        return self::findSubdirsWebPath($root);
    }

    /**
     * 查找IIS站点真实运行目录
     * @param $path
     * @return string
     */
    protected static function findSubdirsWebPath($path)
    {
        if (file_exists("$path/web.config")) {
            return $path;
        } else {
            $dirs = scandir($path);
            foreach ($dirs as $key => $dir) {
                if (is_dir("$path/$dir") && !in_array($dir, ['.', '..'])) {
                    if (file_exists("$path/$dir/web.config")) {
                        return "$path/$dir";
                    }
                    return self::findSubdirsWebPath($dir);
                }
            }
        }
    }

    /**
     * 获取IIS站点的配置信息
     * @param $siteName
     */
    protected static function getIISConfigInfo($siteName)
    {
        try {
            $cert_file = realpath(self::getBtPanelPath() . "/../temp/ssl/$siteName.pfx");
            # 网站主目录
            $db = DatabaseUtils::initBaoTaSystemDatabase();
            $ch = $db->query("select `path` from sites where name = ?", $siteName)->fetch();
            $run_path = self::getrealIISSitePath($siteName);
            $site_path = $ch['path'];
            $config_path = "$run_path/web.config";
            $ssl_info = self::callPython('get_ssl', ['siteName' => $siteName]);

            $cert_info = [];
            // 格式转换PFX
            if (file_exists($cert_file)) {
                $res = [];
                $openSSL = openssl_pkcs12_read(file_get_contents($cert_file), $res, $ssl_info->data->password);

                // this is the CER FILE
                file_put_contents('CERT.cer', $res['pkey'] . $res['cert'] . implode('', $res['extracerts']));

                $cert_info = openssl_x509_parse(((explode("-----END CERTIFICATE-----", $res['cert'])[0]) . "-----END CERTIFICATE-----"), TRUE);
                $cert_info['valid_from'] = date('Y-m-d H:i:s', $cert_info['validFrom_time_t']);
                $cert_info['valid_to'] = date('Y-m-d H:i:s', $cert_info['validTo_time_t']);
                $cert_info['valid_to_date'] = date('Y-m-d', $cert_info['validTo_time_t']);
                // 判断证书类型
                $cert_class = "DV";
                if (isset($cert_info['subject']['O'])) {
                    $cert_class = "OV";
                }
                if (isset($cert_info['subject']['serialNumber']) && isset($cert_info['subject']['businessCategory'])) {
                    $cert_class = "EV";
                }
                $cert_info['cert_class'] = $cert_class;
            }

            return array(
                "config_file" => $config_path,
                "run_path" => $run_path,
                "has_cert" => file_exists($cert_file),
                "cert_file" => $cert_file,
                "ssl_enabled" => $ssl_info->status,
                "cert_info" => $cert_info,
                "is_redirect_https" => $ssl_info->httpTohttps
            );
        } catch (\Exception $exception) {
            die($exception->getMessage());
        }
    }

    /**
     * 访问PhpUtils方法
     * @param $func
     * @param array $data
     * @return mixed
     */
    public static function callPython($func, $data = [])
    {
        $cmd = DatabaseUtils::findValidPythonExecutedPath() . " " . realpath(__DIR__ . "/../PhpUtils.py") . " -f $func -d " . str_replace('"', '\"', json_encode($data));
        exec($cmd, $rcc);
        return json_decode($rcc[0]);
    }

    /**
     * 查询本地站点的证书信息
     * @param $siteName
     * @return array
     * @throws TokenSSLException
     */
    public static function getVhostConfigInfo($siteName)
    {
        if (getenv('BT_PANEL') != "") {
            if (self::getWebserver() === "iis") {
                return self::getIISConfigInfo($siteName);
            }
        }
        // 常见Web服务器
        $configs = self::getConfigPath() . "$siteName.conf";
        $config_file = $configs;
        $has_cert = false;
        $cert_file = "";
        $key_file = "";
        $ssl_enabled = false;
        $cert_info = [];
        $is_redirect_https = false;
        $cert_class = "DV";
        $runPath = self::getVhostRunPath($configs);
        $lines = file($configs);
        foreach ($lines as $key => $line) {
            # 检查是否配置了SSL证书
            if ($ssl = strpos($line, "listen 443") !== false) {
                if (strpos($ecn = substr($line, 0, $ssl), "#") === false) {
                    $ssl_enabled = TRUE;
                }
            }
            # 检查证书文件
            if ($ssl = strpos($line, "ssl_certificate ") !== false) {
                if (strpos(substr($line, 0, $ssl), "#") === false) {
                    # 存在证书文件
                    $has_cert = true;
                    $ep = strpos($line, ";");
                    $dk = strpos($line, "ssl_certificate");
                    $ed = substr($line, $dk, $ep);
                    $es = substr($ed, 0, -1);
                    $e = explode(" ", $es);
                    $cert_file = trim(str_replace(
                        ";",
                        "",
                        $s = str_replace(PHP_EOL, "", end($e))
                    ));
                    // 修复Windows Nginx 配置文件相对路径问题
                    if (getenv('BT_PANEL') != "" && self::getWebserver() === "nginx") {
                        $cert_file = dirname($configs) . "/../" . $cert_file;
                    }
                    $cert_info = openssl_x509_parse(((explode("-----END CERTIFICATE-----", file_get_contents($cert_file))[0]) . "-----END CERTIFICATE-----"), TRUE);
                    $cert_info['valid_from'] = date('Y-m-d H:i:s', $cert_info['validFrom_time_t']);
                    $cert_info['valid_to'] = date('Y-m-d H:i:s', $cert_info['validTo_time_t']);
                    $cert_info['valid_to_date'] = date('Y-m-d', $cert_info['validTo_time_t']);
                    // 判断证书类型
                    if (isset($cert_info['subject']['O'])) {
                        $cert_class = "OV";
                    }
                    if (isset($cert_info['subject']['serialNumber']) && isset($cert_info['subject']['businessCategory'])) {
                        $cert_class = "EV";
                    }
                    $cert_info['cert_class'] = $cert_class;
                }
            }
            # 私钥
            if ($ssl = strpos($line, "ssl_certificate_key") !== false) {
                if (strpos(substr($line, 0, $ssl), "#") === false) {
                    # 存在证书文件
                    $smk = strpos($line, ";");
                    $ek2 = strpos($line, "ssl_certificate_key");
                    $sks = substr($line, $ek2, $smk);
                    $sw = substr($sks, 0, -1);
                    $sx = explode(" ", $sw);
                    $sc = end($sx);
                    $sa = str_replace(PHP_EOL, "", $sc);
                    $key_file = trim(str_replace(";", "", $sa));
                }
            }
            # 是否开启了强制跳转
            if ($ssl = strpos($line, '$server_port !~ 443') !== false) {
                if (strpos($eols = substr($line, 0, $ssl), "#") === false) {
                    $is_redirect_https = TRUE;
                }
            }
            // 修复Windows Apache 配置文件相对路径文件
            if (getenv('BT_PANEL') != "" && self::getWebserver() === "apache") {
                if (strpos($line, "SSLEngine On") !== false) {
                    $ssl_enabled = true;
                }
            }
        }
        // 修复Windows Apache 配置文件相对路径文件
        if (getenv('BT_PANEL') != "" && self::getWebserver() === "apache") {
            $cert_file = dirname($configs) . "/../ssl/$siteName/fullchain.pem";
            $key_file = dirname($configs) . "/../ssl/$siteName/privkey.pem";
            if (file_exists($cert_file) && file_exists($key_file)) {
                $has_cert = true;
            }
            $cert_info = openssl_x509_parse(((explode("-----END CERTIFICATE-----", file_get_contents($cert_file))[0]) . "-----END CERTIFICATE-----"), TRUE);
            $cert_info['valid_from'] = date('Y-m-d H:i:s', $cert_info['validFrom_time_t']);
            $cert_info['valid_to'] = date('Y-m-d H:i:s', $cert_info['validTo_time_t']);
            $cert_info['valid_to_date'] = date('Y-m-d', $cert_info['validTo_time_t']);
            // 判断证书类型
            if (isset($cert_info['subject']['O'])) {
                $cert_class = "OV";
            }
            if (isset($cert_info['subject']['serialNumber']) && isset($cert_info['subject']['businessCategory'])) {
                $cert_class = "EV";
            }
            $cert_info['cert_class'] = $cert_class;
        }

        return array(
            "config_file" => $config_file,
            "run_path" => $runPath,
            "has_cert" => $has_cert,
            "cert_file" => $cert_file,
            "key_file" => $key_file,
            "ssl_enabled" => $ssl_enabled,
            "cert_info" => $cert_info,
            "is_redirect_https" => $is_redirect_https
        );
    }

    /**
     * 根据路径创建验证文件
     * 
     * @param $sitePath
     * @param $validationPath
     * @param $fileName
     * @param $content
     * @return bool
     */
    public static function writeValidationFile($sitePath, $validationPath, $fileName, $content)
    {
        if (is_dir($sitePath) == false) {
            return false;
        }
        $fullValidationPath = $sitePath . '/' . $validationPath;
        // 检查并创建验证目录
        if (is_dir(realpath($fullValidationPath)) === FALSE) {
            mkdir($sitePath . '/' . $validationPath, 0755, TRUE);
        }
        // 创建验证文件
        if (is_file($fullValidationPath . "/$fileName") === true) {
            unlink($fullValidationPath . "/$fileName");
        }
        file_put_contents($fullValidationPath . "/$fileName", $content);
    }
}
