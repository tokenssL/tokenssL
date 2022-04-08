#!/usr/bin/python
# coding: utf-8
# -------------------------------------------------------------------
# tokenssL AutoRenewal Client For 宝塔Linux面板
# -------------------------------------------------------------------
# Copyright (c) 2020-2099 Token SSL™ All rights reserved.
# -------------------------------------------------------------------
# Author: tokenssL <partners@tokenssL.com>
# -------------------------------------------------------------------

# -------------------------------------------------------------------
# tokenssL Uninstall.py
# -----------------------------------------------------------------

import os
import sys
import requests

# 初始化获取面板运行路径
def init_panel_path():
    if os.path.isdir("/www/server/panel"):
        panel_path = "/www/server/panel"
    elif os.path.isdir(os.getenv('BT_PANEL')):
        panel_path = os.getenv('BT_PANEL')
    else:
        panel_path = "/www/server/panel"
        public.bt_print("出错了, 因为系统查找BT-Panel主目录失败, 可能是因为您使用了自定义的目录或当前系统版本不支持")
    sys.path.append(os.getcwd())
    sys.path.append(panel_path)
    os.chdir(panel_path)
    return panel_path


panelPath = init_panel_path()


if not 'class/' in sys.path:
    sys.path.insert(0, 'class/')
requests.DEFAULT_TYPE = 'curl'
from shutil import copyfile

try:
    import public
    from crontab import crontab
except:
    pass

try:
    import psutil
except:
    try:
        public.ExecShell("pip install psutil")
        import psutil
    except:
        public.ExecShell("pip install --upgrade pip")
        public.ExecShell("pip install psutil")
        import psutil

try:
    import OpenSSL
except:
    public.ExecShell("pip install pyopenssl")
    import OpenSSL

try:
    import dns.resolver
except:
    public.ExecShell("pip install dnspython")
    import dns.resolver
try:
    import sqlite3
except:
    public.ExecShell("pip install sqlite3")
    import sqlite3
try:
    import flask
except:
    public.ExecShell("pip install flask")
try:
    import flask_session
except:
    public.ExecShell("pip install flask_session")
try:
    import flask_sqlalchemy
except:
    public.ExecShell("pip install flask_sqlalchemy")


def get_baota_database():
    conn = sqlite3.connect(panelPath+'/data/default.db')
    return conn


def get_setup_path():
    db = get_baota_database().cursor()
    c = db.execute('select `id` from crontab where `echo` = "a2018809d8268aa4885130e40cf1538a"')
    cron_id = c.fetchall()[0][0]


def uninstall():
    os.popen("/usr/bin/php "+panelPath+"/plugin/tokenssl/src/PythonUtils.php --fun=\"%s\"" % ('uninstallStreamForwarding')).read()
    # 备份数据库文件至目录 ${panelPath}/data/plugin_tokenssl_backup.db
    # 防止证书数据丢失, 待下次安装/升级插件时自动导入旧的数据库文件
    # copyfile(panelPath+'/plugin/tokenssl/databases/main.db', panelPath+'/data/plugin_tokenssl_backup.db')
    # print('已完成数据库备份')
    # 调用Baota API删除已创建的CronTab
    db = get_baota_database().cursor()
    c = db.execute('select `id` from crontab where `echo` = "a2018809d8268aa4885130e40cf1538a"')
    cron_id = c.fetchall()[0][0]
    gets = public.dict_obj()
    gets.id = cron_id
    crontab().DelCrontab(gets)
    print('已删除 tokenSSL 定时任务')


if __name__ == '__main__':
    uninstall()
