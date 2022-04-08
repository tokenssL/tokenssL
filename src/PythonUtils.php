<?php

namespace TokenSSL;

use TokenSSL\Common\CertificateUtils;
use TokenSSL\Common\StreamForwardingUtils;
use TokenSSL\Common\TokenssLService;
use TokenSSL\Repository\SiteRep;

require __DIR__ . '/../vendor/autoload.php';
/**
 * 辅助类, 提供暴露给Python调用的函数入口
 * Class PythonUtils
 * @package TokenSSL
 */
class PythonUtils
{
    /**
     * 尝试进行站点的证书续费
     */
    public static function renewSSLOrder()
    {
        $ops = getopt("", [
            "cert_order_id:",
            "token:",
            "site_id:",
        ]);
        try {
            $site = SiteRep::getSiteById($ops['site_id']);
            if (!$site) {
                echo json_encode([
                    'status' => 'error',
                    'message' => '找不到该站点'
                ]);
                return;
            }

            $checkToken = TokenssLService::validTokenReissue($ops['token'], $site['site_domains']);
            if (!$checkToken || !isset($checkToken['success']) || !$checkToken['success'] || !isset($checkToken['data']['unique_value']) || !$checkToken['data']['unique_value']) {
                echo json_encode([
                    'status' => 'error',
                    'message' => 'token验证失败' . !$checkToken ?: json_encode($checkToken),
                ]);
            }
            $result = CertificateUtils::renewSiteSSL($ops['cert_order_id'], $ops['token'], $ops['site_id'], $checkToken['data']['unique_value']);
            if ($result !== true) {
                throw new TokenSSLException("尝试续费时可能出现了错误, 该站点续费失败");
            } else {
                // 续费成功了
                echo json_encode(['status' => "success"]);
            }
        } catch (TokenSSLException $exception) {
            echo json_encode([
                'status' => 'error',
                'message' => $exception->getMessage()
            ]);
        }
    }

    /**
     * 根据域名创建新的CSR和KEY
     */
    public static function generateCsrKey()
    {
        $ops = getopt("", [
            "domain:",
        ]);
        $result = CertificateUtils::generateKeyPair($ops['domain'], FALSE);
        echo json_encode($result);
    }

    public static function installStreamForwarding()
    {
        StreamForwardingUtils::installStreamForwarding();
    }

    public static function uninstallStreamForwarding()
    {
        StreamForwardingUtils::uninstallStreamForwarding();
    }
}

// Main Processing
try {
    $utils = new PythonUtils();
    $params = getopt('', [
        'fun:'
    ]);
    $function = $params['fun'];
    if (is_callable([$utils, $function], true, $callable_name)) {
        call_user_func($callable_name);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'function not found!']);
    }
} catch (\Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
