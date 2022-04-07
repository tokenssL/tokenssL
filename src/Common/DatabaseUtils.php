<?php

namespace TokenSSL\Common;

use Nette\Database\Connection;

class DatabaseUtils
{
    /**
     * Boot Local Sqlite File
     * @return Connection
     */
    public static function initLocalDatabase()
    {
        $dbPath = realpath(__DIR__ . '/../../databases/main.db');
        $database = new Connection("sqlite:$dbPath");
        return $database;
    }

    /**
     * Boot BaoTa System Sqlite File
     * @return Connection
     */
    public static function initBaoTaSystemDatabase()
    {
        $dbPath = realpath(__DIR__ . '/../../../../data/default.db');
        $database = new Connection("sqlite:$dbPath");
        return $database;
    }

    /**
     * 初始化插件的数据库结构
     */
    public static function installDatabase()
    {
        $db = self::initLocalDatabase();
        // 存储配置信息表
        $db->query('
create table if not exists token (
	token text not null constraint token_pk primary key,
    callback_url text not null
);');
        // 存储配置信息表
        $db->query('
create table if not exists configuration (
	setting text not null constraint configuration_pk primary key,
	value text not null
);');
        // 存储证书信息的表
        $db->query('
create table certificate (
	id integer constraint certificate_pk primary key autoincrement,
	site_id integer not null,
	status text not null,
    token char(32) not null,
    type text not null,
	period char(15),
	domains text,
	is_auto_renew integer default 1 not null,
	domain_count integer default 1 not null,
	dcv_info text,
	csr_code text,
	cert_code text,
	key_code text,
	valid_from timestamp,
	valid_till timestamp,
    renew_till timestamp,
	vendor_id text,
    renew_id integer default null,
    deploy_status integer default 0 not null,
	created_at timestamp default current_timestamp not null
);

create unique index certificate_id_uindex on certificate (id);

create unique index certificate_site_id_uindex on certificate (site_id);

');
        // 保存日志信息的表
        $db->query('create table logs
(
	id integer constraint logs_pk primary key autoincrement,
	status text not null,
	title text not null,
	description text not null,
	certificate_id integer,
	created_at timestamp default current_timestamp
);

create unique index logs_id_uindex
	on logs (id);

');
    }

    /**
     * 设置自动化任务
     */
    public static function installCronJob()
    {
        $db = self::initBaoTaSystemDatabase();
        $echo = "5eeb48072b7a0fc713483bd5ade1d59d";
        $check = $db->query("select `id` from crontab where `name` = ?", ("tokenssL™ 证书自动化"))->fetch();
        // Windows系统
        if (is_dir(getenv("BT_PANEL")) && empty($check)) {
            $pyEnv = self::findValidPythonExecutedPath();
            $db->query("INSERT INTO `crontab` ?", [
                'echo' => $echo,
                'name' => "tokenssL™ 证书自动化",
                'type' => "minute-n",
                'where1' => "1",
                'where_hour' => "",
                'where_minute' => "",
                'addtime' => date('Y-m-d H:i:s', time()),
                'status' => 1,
                'save' => "",
                'sName' => "",
                'backupTo' => "localhost",
                'sBody' => "$pyEnv  " . NginxVhostUtils::getBtPanelPath() . "/plugin/tokenssl/src/AutoRenew.py",
                'sType' => 'toShell',
                'urladdress' => "",
            ]);
            // 写入宝塔任务
            $shellContent = "$pyEnv " . NginxVhostUtils::getBtPanelPath() . "/plugin/tokenssl/src/AutoRenew.py >>" . NginxVhostUtils::getBtPanelPath() . "/../cron/$echo.log 2>&1 && echo ----------------------------------------------------------------------------  >> " . NginxVhostUtils::getBtPanelPath() . "/../cron/$echo.log 2>&1 && echo %date%  %time%  Successful >> " . NginxVhostUtils::getBtPanelPath() . "/../cron/$echo.log 2>&1 && echo ----------------------------------------------------------------------------";
            if (!is_dir(NginxVhostUtils::getBtPanelPath() . "/../cron/")) {
                mkdir(NginxVhostUtils::getBtPanelPath() . "/../cron/");
            }
            $baseShell = NginxVhostUtils::getBtPanelPath() . "/../cron/" . $echo;
            file_put_contents($baseShell, str_replace("\r\n", "\n", $shellContent));

            self::reloadCrond();
            LogUtils::writeLog('success', 'cron_setup', '设置定时任务成功PythonEnv: ' . $pyEnv . '');
        }
    }

    /**
     * 查找Python可执行路径
     * @return mixed
     */
    public static function findValidPythonExecutedPath()
    {
        if (substr(strtoupper(PHP_OS), 0, 3) === "WIN") {
            return '"C:\Program Files\python\python.exe"';  // Windows系统
        }
        // 根据优先级定义可用的Python路径
        $initPath = [
            NginxVhostUtils::getBtPanelPath() . "/pyenv/bin/python", // Linux独立PythonEnv
            "/usr/bin/python", // Linux默认PyEnv
        ];
        foreach ($initPath as $pyenv) {
            if (file_exists($pyenv) && is_executable($pyenv)) {
                return $pyenv;
            }
        }
    }

    /**
     * 重载Cron任务
     */
    private static function reloadCrond()
    {
        if (is_file('/etc/init.d/crond')) {
            exec('/etc/init.d/crond reload');
        } elseif (is_file("/etc/init.d/cron")) {
            exec('service cron restart');
        } else {
            exec('systemctl reload crond');
        }
    }

    /**
     * @return string[]
     */
    public static function getToken()
    {
        $db = self::initLocalDatabase();
        $row = $db->query('select * from token')->fetch();
        if (empty($row)) {
            return false;
        }
        return $row;
    }
}
