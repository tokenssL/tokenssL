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


# 写此程序PID
def write_pid():
    if not os.path.exists(panelPath+'/plugin/tokenssl/src/autodellogs.pid'):
        with open(panelPath+'/plugin/tokenssl/src/autodellog.pid', 'w') as fs:
            fs.write(str(os.getpid()))
    else:
        with open(panelPath+'/plugin/tokenssl/src/autodellog.pid', 'w') as f:
            f.write(str(os.getpid()))


# 读取本程序PID
def read_pid():
    if os.path.exists(panelPath+'/plugin/tokenssl/src/autodellog.pid'):
        with open(panelPath+'/plugin/tokenssl/src/autodellog.pid', 'r') as f:
            return f.read()
    else:
        return '0'


if __name__ == '__main__':
    if read_pid() != False and int(read_pid()):
        pid = int(read_pid())
        if pid in psutil.pids():
            print("前序任务还未执行完成, 推出: " + str(pid))
        else:
            write_pid()
            print("新任务开始执行")
            # 删除68天之前的日志信息
            AutoRenew.delete_expired_logs()
    else:
        write_pid()
        print("新任务开始执行")
        # 删除68天之前的日志信息
        AutoRenew.delete_expired_logs()
