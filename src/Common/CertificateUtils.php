<?php

namespace TokenSSL\Common;

use blobfolio\domain\domain;
use TokenSSL\TokenSSLException;
use TokenSSL\Repository\SiteRep;

class CertificateUtils
{
    /**
     * 创建全新的SSL证书订单
     * @param $siteId
     * @param $token
     * @param $unique_value
     * @return bool
     * @throws TokenSSLException
     */
    public static function createNewFullSSLOrder($siteId, $token, $unique_value)
    {
        $siteInfo = SiteRep::getSiteById($siteId);
        $db = DatabaseUtils::initLocalDatabase();
        // 检查是否已经存在对应的证书了
        $check = $db->query('select * from certificate where site_id = ? limit 1', $siteId)->fetch();
        if ($check != NULL) {
            throw new TokenSSLException("此站点已经创建过证书");
        }
        // 还需要检查域名的合法性, 并且找出可以申请证书的域名, 以及中文域名的转换
        $domains = $siteInfo['valid_cert_domains'];
        if (empty($domains)) {
            throw new TokenSSLException("请先为站点添加有效的域名");
        }
        // 创建CSR和KEY
        $newCsrAndKey = self::generateKeyPair($domains[0], FALSE); // 创建CSR，默认申请RSA证书

        $csr_code = $newCsrAndKey['csr_code'];
        $csr_arr = explode(PHP_EOL, trim($csr_code));
        array_pop($csr_arr);
        array_shift($csr_arr);
        $csr_arr = str_replace("\n", "", str_replace("\r", "", implode('', $csr_arr)));
        $csr_arr = base64_decode($csr_arr);
        $md5 = strtoupper(md5($csr_arr));
        list($sha256_a, $sha256_b) = str_split(strtoupper(hash('sha256', $csr_arr)), 32);

        // 写入本地验证文件
        $filename = strtoupper($md5) . '.txt';
        $content = strtoupper($sha256_a . $sha256_b) . PHP_EOL . 'trust-provider.com' . PHP_EOL . $unique_value;
        $validationPath = '.well-known/pki-validation/';
        NginxVhostUtils::writeValidationFile($siteInfo['vhost_info']['run_path'], $validationPath, $filename, $content);

        // 准备创建订单
        $row = DatabaseUtils::getToken();

        $orderRlt = TokenssLService::certCreate($token, $unique_value, $newCsrAndKey['csr_code'], $domains, $row['token'], $row['callback_url'], $siteId);
        if ($orderRlt['success'] != true) {
            LogUtils::writeLog(LogUtils::STATUS_SUCCESS, LogUtils::TITLE_CERT_CREATED, "为站点 $siteId 创建新的证书订单失败了，错误信息：" . $orderRlt['message']);
            throw new TokenSSLException($orderRlt['message']);
        }

        $orderRltData = $orderRlt['data'];

        // 写入订单信息和状态到数据库
        $db->query("INSERT INTO certificate", [
            [
                'site_id' => $siteInfo['id'],
                'status' => $orderRltData['cert_status'],
                'token' => $token,
                'period' => $orderRltData['period'],
                'type' => $orderRltData['type'],
                'domains' => json_encode($domains),
                'domain_count' => count($domains),
                'dcv_info' => json_encode($orderRltData['dcv_info']),
                'csr_code' => $newCsrAndKey['csr_code'],
                'key_code' => $newCsrAndKey['key_code'],
                'vendor_id' => $orderRltData['cert_id'],
                'created_at' => $orderRltData['created_at'],
                'renew_till' => $orderRltData['renew_till'],
            ]
        ]);
        LogUtils::writeLog(LogUtils::STATUS_SUCCESS, LogUtils::TITLE_CERT_CREATED, "为站点 $siteId 成功创建新的证书订单");
        return true;
    }

    /**
     * @param $cert_order_id
     * @param $token
     * @param $siteId
     * @param $unique_value
     * @return bool
     * @throws TokenSSLException
     */
    public static function renewSiteSSL($cert_order_id, $token, $siteId, $unique_value)
    {
        $siteInfo = SiteRep::getSiteById($siteId);
        if (!$siteInfo) {
            return;
        }
        $db = DatabaseUtils::initLocalDatabase();
        $order = $db->query('select * from certificate where id=?', ($cert_order_id))->fetch();
        // 还需要检查域名的合法性, 并且找出可以申请证书的域名, 以及中文域名的转换
        $domains = json_decode($order['domains']);
        if (empty($domains)) {
            throw new TokenSSLException("该站点没有域名, 无法续费");
        }
        // 创建CSR和KEY
        $newCsrAndKey = self::generateKeyPair($domains[0], FALSE); // 创建CSR，默认申请RSA证书

        $csr_code = $newCsrAndKey['csr_code'];
        $csr_arr = explode(PHP_EOL, trim($csr_code));
        array_pop($csr_arr);
        array_shift($csr_arr);
        $csr_arr = str_replace("\n", "", str_replace("\r", "", implode('', $csr_arr)));
        $csr_arr = base64_decode($csr_arr);
        $md5 = strtoupper(md5($csr_arr));
        list($sha256_a, $sha256_b) = str_split(strtoupper(hash('sha256', $csr_arr)), 32);

        // 写入本地验证文件
        $filename = strtoupper($md5) . '.txt';
        $content = strtoupper($sha256_a . $sha256_b) . PHP_EOL . 'trust-provider.com' . PHP_EOL . $unique_value;
        $validationPath = '.well-known/pki-validation/';
        NginxVhostUtils::writeValidationFile($siteInfo['vhost_info']['run_path'], $validationPath, $filename, $content);

        // 准备创建订单
        $row = DatabaseUtils::getToken();
        $orderRlt = TokenssLService::certCreate($token, $unique_value, $newCsrAndKey['csr_code'], $domains, $row['token'], $row['callback_url'], $siteId);
        if (!$orderRlt['success']) {
            throw new TokenSSLException($orderRlt['message']);
        }
        $orderRltData = $orderRlt['data'];
        if (!$orderRltData || !isset($orderRltData['tokenssl_id'])) {
            throw new TokenSSLException(json_encode($orderRlt));
        }


        try {
            $db->beginTransaction();
            // 写入订单信息和状态到数据库
            $db->query("INSERT INTO certificate", [
                [
                    'site_id' => $siteInfo['id'],
                    'status' => $orderRltData['cert_status'],
                    'token' => $token,
                    'period' => $orderRltData['period'],
                    'type' => $orderRltData['type'],
                    'domains' => json_encode($domains),
                    'domain_count' => count($domains),
                    'dcv_info' => json_encode($orderRltData['dcv_info']),
                    'csr_code' => $newCsrAndKey['csr_code'],
                    'key_code' => $newCsrAndKey['key_code'],
                    'vendor_id' => $orderRltData['cert_id'],
                    'created_at' => $orderRltData['created_at'],
                    'renew_till' => $orderRltData['renew_till'],
                ]
            ]);

            LogUtils::writeLog(LogUtils::STATUS_SUCCESS, LogUtils::TITLE_CERT_CREATED, "为站点 $siteId 成功创建续费证书订单 ");

            // 更新订单信息和状态到数据库
            $db->query("UPDATE certificate SET", [
                'renew_id' => $db->getInsertId(),
            ], 'WHERE id = ?', $cert_order_id);
            $db->commit();
        } catch (\Exception $e) {
            $db->rollBack();
            throw $e;
        }

        return true;
    }

    /**
     * 重签 SSL 证书
     * @param $siteId
     * @param $unique_value
     * @return bool
     * @throws TokenSSLException
     */
    public static function reissueSSLOrder($siteId, $unique_value)
    {
        // 检查证书是是否存在
        $siteInfo = SiteRep::getSiteById($siteId);
        if (!$siteInfo) {
            throw new TokenSSLException("站点不存在");
        }
        $db = DatabaseUtils::initLocalDatabase();
        // 检查是否已经存在对应的证书了
        $check = $db->query('select * from certificate where `site_id` = ?', $siteInfo['id'])->fetch();
        if ($check == NULL) {
            throw new TokenSSLException("未找到对应的SSL证书, 无法重签");
        }
        // 检查证书状态是否为 issued
        if ($check['status'] !== "issued") {
            throw new TokenSSLException("证书状态不正确, 不可重签");
        }
        // 获取站点信息、域名，检查域名合法性，并移除不正确的域名
        $domains = $siteInfo['valid_cert_domains'];
        // 生成新的CSR和KEY
        $newCsrAndKey = self::generateKeyPair($domains[0], FALSE);

        $csr_code = $newCsrAndKey['csr_code'];
        $csr_arr = explode(PHP_EOL, trim($csr_code));
        array_pop($csr_arr);
        array_shift($csr_arr);
        $csr_arr = str_replace("\n", "", str_replace("\r", "", implode('', $csr_arr)));
        $csr_arr = base64_decode($csr_arr);
        $md5 = strtoupper(md5($csr_arr));
        list($sha256_a, $sha256_b) = str_split(strtoupper(hash('sha256', $csr_arr)), 32);

        // 写入本地验证文件
        $filename = strtoupper($md5) . '.txt';
        $content = strtoupper($sha256_a . $sha256_b) . PHP_EOL . 'trust-provider.com' . PHP_EOL . $unique_value;
        $validationPath = '.well-known/pki-validation/';
        NginxVhostUtils::writeValidationFile($siteInfo['vhost_info']['run_path'], $validationPath, $filename, $content);

        // 准备创建订单
        $row = DatabaseUtils::getToken();

        // 提交API重签
        $orderRlt = TokenssLService::certReissue($check['token'], $unique_value, $newCsrAndKey['csr_code'], $domains, $row['token'], $row['callback_url'], $siteId);

        if (!isset($orderRlt['success'])) {
            throw new TokenSSLException("重签失败, 请稍后重试");
        }
        if (!$orderRlt['success']) {
            throw new TokenSSLException($orderRlt['message']);
        }

        $orderRltData = $orderRlt['data'];

        // 保存新的证书订单信息
        $db->query("update certificate set", [
            'status' => $orderRltData['cert_status'],
            'dcv_info' => json_encode($orderRltData['dcv_info']),
            'csr_code' => $newCsrAndKey['csr_code'],
            'key_code' => $newCsrAndKey['key_code'],
            'vendor_id' => $orderRltData['tokenssl_id'],
            'domains' => json_encode($domains),
            'domain_count' => count($domains),
        ], 'WHERE id = ?', $check['id']);

        LogUtils::writeLog(LogUtils::STATUS_SUCCESS, LogUtils::TITLE_CERT_REISSUE, '成功提交证书重签申请, 站点: ' . $siteInfo['name'], $check['id']);
        // 返回状态
        return $orderRlt;
    }

    /**
     * 创建证书申请信息和密钥对 CSR&KEY
     * @param $common_name
     * @param bool $is_ecc
     * @return array
     */
    public static function generateKeyPair($common_name, $is_ecc = FALSE)
    {
        // 检查是否为IP地址
        $domainSp = new domain($common_name);
        if ($domainSp->is_ip()) {
            $common_name = $common_name . ".in-addr.arpa";
        }
        $subject = array(
            "commonName" => $common_name,
            "organizationName" => "Tls Automation Co, Ltd",
            "organizationalUnitName" => "Certification Division",
            "localityName" => "HK",
            "stateOrProvinceName" => "HK",
            "countryName" => "HK",
        );
        try {
            // Generate a new private (and public) key pair
            if ($is_ecc === FALSE) {
                $private_key = openssl_pkey_new(array('private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048, 'config' => __DIR__ . '/../Config/openssl.cnf'));
                $csr_resource = openssl_csr_new($subject, $private_key, array('digest_alg' => 'sha256', 'config' => __DIR__ . '/../Config/openssl.cnf'));
            } else {
                $private_key = openssl_pkey_new(array('private_key_type' => OPENSSL_KEYTYPE_EC, "curve_name" => 'prime256v1', 'config' => __DIR__ . '/../Config/openssl.cnf'));
                $csr_resource = openssl_csr_new($subject, $private_key, array('digest_alg' => 'sha384', 'config' => __DIR__ . '/../Config/openssl.cnf'));
            }
            openssl_csr_export($csr_resource, $csr_string);
            openssl_pkey_export($private_key, $private_key_string, null, array('config' => __DIR__ . '/../Config/openssl.cnf'));
        } catch (\Exception $exception) {
            die($exception->getMessage());
        }

        return array(
            'csr_code' => $csr_string,
            'key_code' => $private_key_string
        );
    }
}
