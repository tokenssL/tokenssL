#!/usr/bin/python
# coding: utf-8
# -------------------------------------------------------------------
# tokenssL AutoRenewal Client For 宝塔Linux面板
# -------------------------------------------------------------------
# Copyright (c) 2020-2099 tokenssL™ All rights reserved.
# -------------------------------------------------------------------
# Author: tokenssL <partners@tokenssL.com>
# -------------------------------------------------------------------

# -------------------------------------------------------------------
# tokenssL AutoRenewal Client
# -----------------------------------------------------------------

import AutoRenew
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

pid_path=panelPath+'/plugin/tokenssl/pid/deploycert.pid'

# 写此程序PID
def pid_write():
    if not os.path.exists(pid_path):
        with open(pid_path, 'w') as fs:
            fs.write(str(os.getpid()))
    else:
        with open(pid_path, 'w') as f:
            f.write(str(os.getpid()))

# 读取本程序PID
def pid_read():
    if os.path.exists(pid_path):
        with open(pid_path, 'r') as f:
            return f.read()
    else:
        return '0'


if __name__ == '__main__':
    can_excute = False
    pid = pid_read()
    if pid != False and int(pid):
        if int(pid) in psutil.pids():
            print("前序任务还未执行完成, 推出: " + str(pid))
        else:
            can_excute = True
    else:
        can_excute = True
    if can_excute == True:
        pid_write()
        print("新任务开始执行")
        AutoRenew.deploy_issued_cert()
