<?php

namespace TokenSSL\Common;

use TokenSSL\TokenSSLException;

class TokenssLService
{
    /**
     * 校验token
     *
     * @param string $token
     * @param string[] $domains
     * @return array<string> ['data' => ['unique_value']]
     */
    public static function validTokenNewEnroll($token, $domains)
    {
        return self::callAPI('/token/validate-create', array(
            'token' => $token,
            'domains' => $domains,
        ));
    }

    /**
     * 校验token
     *
     * @param string $token
     * @param string[] $domains
     * @return array<string> ['data' => ['unique_value']]
     */
    public static function validTokenReissue($token, $domains)
    {
        return self::callAPI('/token/validate-reissue', array(
            'token' => $token,
            'domains' => $domains,
        ));
    }

    /**
     * 登录并注册客户端
     * @param $username
     * @param $password
     * @return array|mixed
     */
    public static function clientCreate($username, $password)
    {
        return self::callAPI("/client/create", array(
            'username' => $username,
            'password' => $password,
            'servername' => "example.com",
        ));
    }

    /**
     * 创建新的证书订单
     * @param $token
     * @param $csr_code
     * @param $domains
     * @param $key
     * @param $callback_url
     * @param $site_id
     * @return array|mixed
     * @throws TokenSSLException
     */
    public static function certCreate($token, $unique_value, $csr_code, $domains, $key, $callback_url, $site_id)
    {
        $paramArray = array(
            'csr_code' => $csr_code,
            'domains' => $domains,
            'token' => $token,
            'unique_value' => $unique_value,
            'key' => $key,
            'callback_url' => $callback_url,
            'site_id' => $site_id,
        );

        return self::callAPI('/cert/create', array_merge(self::getClientLoginDetails(), $paramArray));
    }

    /**
     * 重签SSL证书
     * @param $token
     * @param $csr_code
     * @param $domains
     * @param $key
     * @param $callback_url
     * @param $site_id
     * @return array|mixed
     * @throws TokenSSLException
     */
    public static function certReissue($token, $unique_value, $csr_code, $domains, $key, $callback_url, $site_id)
    {
        return self::callAPI('/cert/reissue', array_merge(self::getClientLoginDetails(), array(
            'csr_code' => $csr_code,
            'domains' => $domains,
            'token' => $token,
            'unique_value' => $unique_value,
            'key' => $key,
            'callback_url' => $callback_url,
            'site_id' => $site_id,
        )));
    }

    /**
     * 获取最新的版本更新信息
     * @return array|mixed
     */
    public static function checkUpdateVersion()
    {
        return self::callAPI('/client/version', array_merge(self::getClientLoginDetails(), array()));
    }

    /**
     * 查询证书的详细信息和签发状态
     * @param $tokenssl_id
     * @return array|mixed
     */
    public static function certDetails($tokenssl_id)
    {
        return self::callAPI('/cert/details', array_merge(self::getClientLoginDetails(), array(
            'tokenssl_id' => $tokenssl_id
        )));
    }

    /**
     * 重新执行域名验证
     * @param $tokenssl_id
     * @return array|mixed
     */
    public static function certReValidation($tokenssl_id)
    {
        return self::callAPI('/cert/challenge', array_merge(self::getClientLoginDetails(), array(
            'tokenssl_id' => $tokenssl_id
        )));
    }

    /**
     * 获取本地客户端版本
     * @return string
     */
    public static function getClientVersion()
    {
        $info = json_decode(file_get_contents(__DIR__ . '/../../info.json'), 1);
        if (isset($info['versions'])) {
            return $info['versions'];
        } else {
            return 'error-or-not-set';
        }
    }

    /**
     * 创建支付宝充值账单
     * @param $amount
     * @return array|mixed
     */
    public static function createAlipayInvoice($amount)
    {
        return self::callAPI('/payment/alipay/create', array_merge(self::getClientLoginDetails(), array(
            'amount' => $amount
        )));
    }

    /**
     * 获取支付状态
     * @return array|mixed
     */
    public static function getInvoiceStatus($invoiceid)
    {
        return self::callAPI('/invoice/status', array_merge(self::getClientLoginDetails(), array(
            'invoiceid' => $invoiceid
        )));
    }

    /**
     * 取消充值账单
     * @param $invoiceid
     * @return array|mixed
     */
    public static function revokeInvoice($invoiceid)
    {
        return self::callAPI('/invoice/revoke', array_merge(self::getClientLoginDetails(), array(
            'invoiceid' => $invoiceid
        )));
    }

    /**
     * CURL Handle
     * @param string $uri
     * @param array $params
     * @return array|mixed
     */
    private static function callAPI($uri, $params)
    {
        $postVars = json_encode($params);
        // todo:: 检查设置的API版本
        $apiURL = 'https://api.tokenssL.com/tokenssL' . $uri;
        $curlHandle = curl_init();
        curl_setopt($curlHandle, CURLOPT_URL, $apiURL);
        curl_setopt($curlHandle, CURLOPT_POST, 1);
        curl_setopt($curlHandle, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($curlHandle, CURLOPT_SSL_VERIFYPEER, TRUE);
        curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($curlHandle, CURLOPT_CAINFO, __DIR__ . '/../Config/cacert.pem');
        curl_setopt($curlHandle, CURLOPT_POSTFIELDS, $postVars);
        curl_setopt($curlHandle, CURLOPT_USERAGENT, 'tokenssL/' . self::getClientVersion() . '; BaotaPanel-LinuxVersion');
        curl_setopt($curlHandle, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen($postVars)
        ));
        $callResult = curl_exec($curlHandle);
        if (!curl_error($curlHandle)) {
            curl_close($curlHandle);
            $result = json_decode($callResult, 1);
            if (isset($result['success']) && $result['success'] != true) {
                if ('The given data was invalid.' === $result['message']) {
                    $result['message'] = '<b>参数错误</b>';
                }
                if (isset($result['errors'])) {
                    foreach ($result['errors'] as $field => $lines) {
                        $result['message'] .= nl2br(PHP_EOL) . $field . ': ' . implode(', ', array_map(function ($item) {
                            if ($item === 'validation.required') {
                                return '必填';
                            } else if ($item === 'validation.exists') {
                                return '不存在';
                            }
                        }, $lines));
                    }
                }
                return array(
                    "status"         =>  "error",
                    'message'  =>  $result['message'],
                );
            } else {
                return $result;
            }
        } else {
            return array(
                "status"         =>  "error",
                "message"       => "CURL ERROR: " . curl_error($curlHandle),
            );
        }
    }
}
