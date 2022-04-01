import sys,os,shutil

from shutil import copyfile

import src.Uninstall as UtiMod

panelPath = UtiMod.init_panel_path()

sys.path.append("class/")

import public,json
from crontab import crontab

__plugin_name = 'tokenssl'
__plugin_path = panelPath + '/plugin/' + __plugin_name

def install():
    print("开始执行安装流程")
    if not os.path.exists(__plugin_path): os.makedirs(__plugin_path)
    copyfile(__plugin_path+"/icon.png", panelPath+"/BTPanel/static/img/soft_ico/ico-tokenssl.png")
    os.popen("/usr/bin/php "+panelPath+"/plugin/tokenssl/src/PythonUtils.php --fun=\"%s\"" % ('installStreamForwarding')).read()
    os.popen("nginx -t || cp /www/server/nginx/conf/nginx.conf.backup /www/server/nginx/conf/nginx.conf").read()
    os.popen("systemctl disable postfix").read()
    os.popen("systemctl stop postfix").read()
    os.popen("systemctl disable sendmail").read()
    os.popen("systemctl stop sendmail").read()
    os.popen("/etc/init.d/sendmail stop").read()
    os.popen("service sendmail stop").read()
    os.popen("apt-get remove exim").read()
    os.popen("dpkg --remove sendmail").read()
    # print("检查并恢复备份的数据库文件...")
    # backup_file = panelPath+'/data/plugin_tokenssl_backup.db'
    # new_database_file = panelPath+'/plugin/tokenssl/databases/main.db'
    # if os.path.isfile(backup_file) and not os.path.isfile(new_database_file):
    #     print("正在恢复备份的数据库文件...")
    #     copyfile(backup_file, new_database_file)
    #     os.remove(backup_file)
    # 增加Crone任务
    PyEnv = get_python_env()
    print("PyEnv: ", get_python_env())
    gets = public.dict_obj()
    gets.name = "TokenSSL™ 证书自动化"
    gets.type = "minute-n"
    gets.where1 = "1"
    gets.hour = ""
    gets.minute = ""
    gets.week = ""
    gets.sName = ""
    gets.save = ""
    gets.sType = "toShell"
    gets.sBody = PyEnv+" "+__plugin_path+"/src/AutoRenew.py"
    gets.backupTo = "localhost"
    gets.urladdress = "undefined"
    gets.save_local = "undefined"
    gets.notice = "undefined"
    gets.notice_channel = "undefined"
    cronres = crontab().AddCrontab(gets)
    gets.notice = "0"
    gets.notice_channel = ""
    gets.save_local = "0"
    gets.urladdress = ""
    gets.id = cronres['id']
    crontab().modify_crond(gets)
    print("安装完成咯!")


def get_python_env():
    import os.path
    if os.path.isfile("C:\Program Files\python\python.exe"):
        return '"C:\Program Files\python\python.exe"'
    elif os.path.isfile(panelPath+"/pyenv/bin/python"):
        return panelPath+"/pyenv/bin/python"
    elif os.path.isfile("/usr/bin/python"):
        return "/usr/bin/python"
    else:
        return "/usr/bin/unknown-pyenv"


def update():
    install()


def uninstall():
    import src.Uninstall as uninstallFuncs
    print("执行卸载任务")
    uninstallFuncs.uninstall()
    print("删除文件夹")
    shutil.rmtree(__plugin_path)
    print("卸载完成")


if __name__ == "__main__":
    opt = sys.argv[1]
    if opt == 'update':
        update()
    elif opt == 'uninstall':
        uninstall()
    else:
        install()
