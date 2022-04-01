<?php

namespace TokenSSL\Controller;

//_post()会返回所有POST参数，要获取POST参数中的username参数，请使用 _post('username')
//可通过_version()函数获取面板版本
//可通过_post('client_ip') 来获取访客IP

//常量说明：
//PLU_PATH 插件所在目录
//PLU_NAME 插件名称
//PLU_FUN  当前被访问的方法名称
use TokenSSL\Common\CertificateUtils;
use TokenSSL\Common\DatabaseUtils;
use TokenSSL\Common\TokenssLService;
use TokenSSL\Common\TwigUtils;
use TokenSSL\TokenSSLException;
use TokenSSL\TokenSSLPageException;
use TokenSSL\Common\LogUtils;
use TokenSSL\Repository\SiteRep;

/**
 * Class MainController
 * @package TokenSSL\Controller
 */
class MainController
{
    /**
     * @var \Twig\Environment
     */
    protected $twig;

    /**
     * MainController constructor.
     */
    public function __construct()
    {
        try {
            // 启动Twig引擎
            $this->twig = TwigUtils::initTwig();
            // 检查和初始化数据库
            $this->checkAndInstallDatabase();
            // 检查并设置自动化任务
            DatabaseUtils::installCronJob();
        } catch (\Exception $exception) {
            die($exception->getMessage());
        }
    }

    /**
     * @return string
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     */
    public function getSupport()
    {
        // $this->checkInitAcc();
        return $this->twig->render('getSupport.html.twig');
    }

    /**
     * @return string
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     */
    public function getOnlineSupport()
    {
        // $this->checkInitAcc();
        return $this->twig->render('onlineSupport.html.twig');
    }

    /**
     * @return string
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     */
    public function siteList()
    {
        $data = [
            'initializeRequired' => !DatabaseUtils::getToken(),
            'ip' => '',
            'ip2' => $this->getIp(),
        ];
        if ($data['initializeRequired']) {
            $data['ip'] = trim(gethostbyname('smtp.forwarding.tokenssl.com'));
        }

        return $this->twig->render('siteList.html.twig', $data);
    }

    public function setToken()
    {
        $db = DatabaseUtils::initLocalDatabase();

        $data = [];
        $data['callback_url'] = _post('callback_url');

        if (!file_exists('/www/server/panel/config/api.json')) {
            return ['success' => true,];
        }
        $apikey = file_get_contents('/www/server/panel/config/api.json');
        if (!trim($apikey)) {
            return ['success' => true,];
        }
        $api = json_decode($apikey, true);
        if (!isset($api['token_crypt'])) {
            return ['success' => true,];
        }

        $data['token'] = $api['token_crypt'];

        if (DatabaseUtils::getToken()) {
            $db->query('update token set ', $data);
        } else {
            $db->query('insert into token ?', $data);
        }

        $request_time = time();
        $request_token = md5($request_time . '' . md5($data['token']));

        $parse = parse_url($data['callback_url']);
        $uri = $parse['scheme'] . '://' . $parse['host'] . ':' . $parse['port'] . '/firewall?action=AddAcceptPort&request_time=' . $request_time . '&request_token=' . $request_token;
        $data = [
            'port' => '25',
            'type' => 'port',
            'ps' => 'SMTP服务',
        ];
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $uri);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_exec($ch);
        curl_close($ch);

        return ['success' => true,];
    }

    /**
     * 初始化账户
     * @return string
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     */
    public function getInitAccount()
    {
        return $this->twig->render('getAccountWelcome.html.twig');
    }

    /**
     * 移除证书订单
     * @return array
     */
    public function removeSSLOrder()
    {
        try {
            $site = SiteRep::getSiteById(_post('siteId'));
            $db = DatabaseUtils::initLocalDatabase();
            $db->query('delete from certificate where site_id = ?', $site['id']);
            return ['status' => "success", "message" => "订单删除成功"];
        } catch (\Exception $e) {
            return ['status' => "error", "message" => '删除订单时出错：' . $e->getMessage()];
        }
    }

    /**
     * 重新执行域名验证
     * @return array
     */
    public function recheckDomainValidation()
    {
        try {
            $site = SiteRep::getSiteById(_post('siteId'));
            $db = DatabaseUtils::initLocalDatabase();
            // 查询当前状态
            $order = $db->query("select * from certificate where site_id = ?", ($site['id']))->fetch();
            if (empty($order)) {
                return  ['status' => 'error', 'message' => "不存在对应的证书订单"];
            }
            // 执行远端的API订单验证检查
            return TokenssLService::certReValidation($order->vendor_id, _post('challenge_type'));
        } catch (\Exception $e) {
            return ['status' => "error", "message" => '操作失败：' . $e->getMessage()];
        }
    }

    /**
     * 检查最新的客户端版本
     * @return array
     */
    public function checkUpdateVersion()
    {
        try {
            $localVersion = TokenssLService::getClientVersion();
            $rep = TokenssLService::checkUpdateVersion();
            if (isset($rep['latest_version']) && $rep['latest_version'] > $localVersion) {
                return [
                    'status' => 'success',
                    'updateVersion' => $rep['latest_version'],
                    'updateDescription' => $rep['update_description'],
                ];
            }
            return '';
        } catch (\Exception $e) {
            return ['status' => "error", "message" => '操作失败：' . $e->getMessage()];
        }
    }

    /**
     * 切换自动续费状态
     * @return array
     */
    public function toggleAutoRenewal()
    {
        $site = SiteRep::getSiteById(_post('siteId'));
        $db = DatabaseUtils::initLocalDatabase();
        // 查询当前状态
        $order = $db->query("select * from certificate where site_id = ?", ($site['id']))->fetch();
        if (empty($order)) {
            return  ['status' => 'error', 'message' => '该站点未配置自动化证书'];
        }
        if ($order['is_auto_renew'] === 0) {
            $status = 1;
        } else {
            $status = 0;
        }
        $db->query('update certificate set', [
            'is_auto_renew' => $status,
        ], 'WHERE id=?', $order['id']);
        LogUtils::writeLog(LogUtils::STATUS_SUCCESS, 'auto_renew_toggle', '成功' . ($status === 1 ? '开启' : '关闭') . '站点' . _post('siteName') . '的自动续费', $order['id']);
        return ['status' => 'success', 'message' => '自动续费已' . ($status === 1 ? '开启' : '关闭')];
    }

    /**
     * 轮训检查证书是否已经签发
     * @return array
     */
    public function checkSSLOrderStatus()
    {
        $site = SiteRep::getSiteById(_post('siteId'));
        $db = DatabaseUtils::initLocalDatabase();
        // 查询当前状态
        $order = $db->query("select * from certificate where site_id = ?", ($site['id']))->fetch();
        if (empty($order) || $order['status'] !== "issued") {
            return  ['status' => 'error'];
        }
        if ($order['status'] === "issued") {
            return  ['status' => 'success'];
        }
    }

    /**
     * 网站详情页面
     * @return mixed
     */
    public function siteSetting()
    {
        $bt_ip = null;
        $site = SiteRep::getSiteById(_post('siteId'));
        // 获取 TokenSSL 证书订单
        $db = DatabaseUtils::initLocalDatabase();
        $order = $db->query("select * from certificate where site_id = ?", ($site['id']))->fetch();
        $has_ip = false;
        $has_wildcard = false;

        if (isset($order->domains)) {
            $order->domains = json_decode($order->domains);
            foreach ($order->domains as $domain) {
                if (filter_var($domain, FILTER_VALIDATE_IP)) {
                    $has_ip = true;
                }

                if (strpos($domain, '*') !== false) {
                    $has_wildcard = true;
                }

                if ($has_ip && $has_wildcard) {
                    break;
                }
            }
        }

        if ($order['status'] == 'processing') {
            $bt_ip = $this->getIp();
        }
        //检出域名验证信息
        $dcvFormat = [];
        $dcvInfo = json_decode($order->dcv_info, 1);
        foreach ($dcvInfo as $domain => $info) {
            $hosta = explode('.', $info['dns_host']);
            $dcvFormat['dns_host'] = $hosta[0];
            $dcvFormat['dns_type'] = $info['dns_type'];
            $dcvFormat['dns_value'] = $info['dns_value'];
            $dcvFormat['http_path'] = str_replace('http://' . $domain, '', $info['http_verifylink']);
            $dcvFormat['http_filename'] = $info['http_filename'];
            $dcvFormat['http_filecontent'] = $info['http_filecontent'];
            break;
        }

        return $this->twig->render('siteSetting.html.twig', [
            'site' => $site,
            'ssl_order' => $order,
            'dcv_data' => $dcvFormat,
            'bt_ip' => $bt_ip,
            'has_ip' => $has_ip,
            'has_wildcard' => $has_wildcard,
        ]);
    }

    protected function getIp()
    {
        $db = DatabaseUtils::initLocalDatabase();
        $row = $db->query('select value from configuration where setting=?', 'ip') ->fetch();
        $ip = null;
        if ($row) {
            $ip = $row['value'];
        }
        if (!$ip) {
            $ip = trim(file_get_contents('https://api.ip.sb/ip'));
            $db->query('insert into configuration ?', ['value' => $ip, 'setting' => 'ip']);
        }

        return $ip;
    }

    /**
     * 签发中，刷新状态
     */
    public function refreshOrder()
    {
        $site_id = _post('siteId');
        if (!$site_id) {
            return [
                'status' => 'error',
                'message' => '站点ID不能为空',
            ];
        }

        $db = DatabaseUtils::initLocalDatabase();
        $db = $db->query("select status from certificate where site_id = ?", $site_id)->fetch();
        return [
            'status' => 'success',
            'message' => '操作成功',
            'cert' => [
                'status' => $db['status'],
            ]
        ];
    }

    /**
     * 证书创建页面
     * @return string
     * @throws TokenSSLPageException
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     */
    public function createSSLOrder()
    {
        $site = SiteRep::getSiteById(_post('siteId'));
        return $this->twig->render('createSSLOrder.html.twig', [
            'site' => $site,
        ]);
    }

    /**
     * 检查Token
     * @return array
     * @throws TokenSSLPageException
     * @throws \Twig\Error\LoaderError
     */
    public function checkToken()
    {
        try {
            $site = SiteRep::getSiteById(_post('siteId'));
            $check = TokenssLService::validTokenNewEnroll(_post('token'), $site['site_domains']);
            if ($check['success']) {
                return [
                    'status' => 'success',
                    'message' => '通过检测',
                    'unique_value' => $check['data']['unique_value'],
                    'period' => $check['data']['period'],
                ];
            }
            return [
                'status' => 'error',
                'message' => 'Token不存在'
            ];
        } catch (TokenSSLException $exception) {
            throw new TokenSSLPageException("错误", $exception->getMessage());
        }
    }

    /**
     * 确认签发+部署证书
     * 
     * @return array
     * @throws TokenSSLPageException
     * @throws \Twig\Error\LoaderError
     */
    public function siteSSLdeploy()
    {
        $site = SiteRep::getSiteById(_post('siteId'));
        $domains = $site['site_domains'];

        $wildcard = 0;
        $normal = 0;
        $ipv4 = 0;
        foreach ($domains as $domain) {
            if (substr($domain, 0, 2) === '*.') {
                $wildcard++;
                continue;
            }
            if (filter_var($domain, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                $ipv4++;
                continue;
            }

            $normal++;
        }
        $san = [];

        if ($wildcard > 0) {
            $san[] = '通配域名×' . $wildcard;
        }
        if ($ipv4 > 0) {
            $san[] = 'IPv4×' . $ipv4;
        }
        if ($normal > 0) {
            $san[] = '普通符×' . $normal;
        }

        return $this->twig->render('siteSSLdeploy.html.twig', [
            'site' => $site,
            'token' => _post('token'),
            'period' => _post('period'),
            'san' => join(' + ', $san),
            'unique_value' => _post('unique_value'),
        ]);
    }

    /**
     * 尝试创建全新的SSL订单
     * @return array
     */
    public function createNewFullSSL()
    {
        try {
            $result = CertificateUtils::createNewFullSSLOrder(_post('siteId'), _post('token'), _post('unique_value'));
            if ($result === true) {
                return ["status" => "success", "message" => "证书订单创建成功, 完成签发后将会自动安装"];
            } else {
                return ["status" => "error", "message" => "订单创建失败, 请您稍后再试"];
            }
        } catch (TokenSSLException $exception) {
            return ["status" => "error", "message" => $exception->getMessage()];
        }
    }

    /**
     * 重签证书订单
     * @return array
     */
    public function reissueSSLOrder()
    {
        try {
            $site = SiteRep::getSiteById(_post('siteId'));
            if (!$site) {
                return ["status" => "error", "message" => "站点不存在"];
            }
            $db = DatabaseUtils::initLocalDatabase();
            $check = $db->query('select * from certificate where `site_id` = ?', $site['id'])->fetch();
            if (!$check) {
                return ["status" => "error", "message" => "该站点没有证书"];
            }
            $checkToken =  TokenssLService::validTokenReissue($check['token'], $site['site_domains']);
            if (!$checkToken || !isset($checkToken['success']) || !$checkToken['success'] || !isset($checkToken['data']['unique_value']) || !$checkToken['data']['unique_value']) {
                echo json_encode([
                    'status' => 'error',
                    'message' => 'token验证失败' . !$checkToken ?: json_encode($checkToken),
                ]);
            }

            $relt = CertificateUtils::reissueSSLOrder(_post('siteId'), $checkToken['data']['unique_value']);

            if ($relt) {
                return ["status" => "success", "message" => "重签名请求已提交"];
            } else {
                return ["status" => "error", "message" => "重签名请求失败, 请稍后再试！"];
            }
        } catch (TokenSSLException $exception) {
            return ["status" => "error", "message" => $exception->getMessage()];
        }
    }
    /**
     * 查看账户绑定和客户端信息页面
     * @return mixed
     */
    public function clientInfo()
    {
        // $this->checkInitAcc();
        $db = DatabaseUtils::initLocalDatabase();
        $clientInfo = $db->query("select * from configuration where `setting` in ('acc_email','client_id','access_token','ip_address','client_status','registered_at')")->fetchAll();
        $info = [];
        foreach ($clientInfo as $setting) {
            $info[$setting['setting']] = $setting['value'];
        }
        $info['version'] = TokenssLService::getClientVersion();
        return $this->twig->render('clientInfo.html.twig', ['client' => $info]);
    }

    private function hasDatabaseInitialized()
    {
        if (!filesize(__DIR__ . '/../../databases/main.db')) {
            @unlink(__DIR__ . '/../../databases/main.db');
        }

        return file_exists(__DIR__ . '/../../databases/main.db');
    }

    /**
     * 检查并创建本地插件数据库
     */
    public function checkAndInstallDatabase()
    {
        if (!$this->hasDatabaseInitialized()) {
            try {
                new \SQLite3(__DIR__ . '/../../databases/main.db');
                DatabaseUtils::installDatabase();
            } catch (\Exception $exception) {
                return $exception->getMessage();
            }
        }
        return "success";
    }

    /**
     * 查看客户端日志
     * @return string
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     */
    public function getClientLogs()
    {
        return $this->twig->render('clientLogs.html.twig');
    }

    /**
     * 获取列表 JQ TableLists
     * @return array|string
     * @throws TokenSSLException
     */
    public function getClientLogList()
    {
        $start = _post('start') == "" ? 0 : _post('start');
        $length = _post('length') == "" ? 6 : (_post('length') > 20 ? 20 : _post('length'));
        $search = _post('search[value]') != "" ? _post('search[value]') : NULL;
        return LogUtils::getClientLogList(_post('draw'), $start, $length, $search);
    }

    /**
     * jQ Databases QuerAPI
     * 查询站点列表
     * @return mixed
     */
    public function getSiteList()
    {
        $start = _post('start') == "" ? 0 : _post('start');
        $length = _post('length') == "" ? 6 : (_post('length') > 20 ? 20 : _post('length'));
        $search = _post('search[value]') != "" ? _post('search[value]') : NULL;
        return SiteRep::getSiteList(_post('draw'), $start, $length, $search);
    }

    public function issueNotify()
    {
        $siteId = _get('siteId');
        $cert = trim(_post('cert'));
        $ca = trim(_post('ca'));

        if (!$siteId || !$cert || !$ca || !openssl_x509_parse($cert)) {
            return -1;
        }
        $site = SiteRep::getSiteById($siteId);
        if (!$site || !$site['id'] || !$site['name']) {
            return -2;
        }

        $db = DatabaseUtils::initLocalDatabase();
        $row = $db->query('select * from certificate where site_id = ?', $siteId)->fetch();
        if (empty($row)) {
            return -3;
        }

        $db->query("UPDATE certificate SET", [
            'cert_code' => $cert . PHP_EOL . $ca,
            'valid_from' => date('Y-m-d H:i:s', strtotime(openssl_x509_parse($cert)['validFrom_time_t'])),
            'valid_till' => date('Y-m-d H:i:s', strtotime(openssl_x509_parse($cert)['validTo_time_t'])),
            'status' => 'issued',
        ], 'WHERE site_id = ?', $siteId);

        $request_time = time();
        $request_token = md5($request_time . '' . md5(DatabaseUtils::getToken()['token']));

        $uri = _post('uri[scheme]') . '://' . _post('uri[host]') . ':' . _post('uri[port]') . '/site?action=SetSSL&request_time=' . $request_time . '&request_token=' . $request_token;
        $data = [
            'type' => 1,
            'siteName' => $site['name'],
            'key' => $row['key_code'],
            'csr' => $cert . PHP_EOL . $ca,
        ];
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $uri);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $res1 = curl_exec($ch);
        curl_close($ch);

        $uri = _post('uri[scheme]') . '://' . _post('uri[host]') . ':' . _post('uri[port]') . '/system?action=ServiceAdmin&request_time=' . $request_time . '&request_token=' . $request_token;
        $data = [
            'name' => 'nginx',
            'type' => 'reload',
        ];
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $uri);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $res2 = curl_exec($ch);
        curl_close($ch);

        return $res1 . $res2;
    }
}
