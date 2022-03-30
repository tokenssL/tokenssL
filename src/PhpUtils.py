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
# tokenssL PHP Migrate API
# -----------------------------------------------------------------

from panelSite import *
import os
import sys
import requests
import getopt
import json
import Uninstall as UtiMod

panelPath = UtiMod.init_panel_path()

if not 'class/' in sys.path:
    sys.path.insert(0, 'class/')
requests.DEFAULT_TYPE = 'curl'


# 获取站点SSL信息
def get_ssl(optionalData=""):
    print(json.dumps(panelSite().GetSSL(DictObj(optionalData)), ensure_ascii=False))


# 获取IISSiteInfo
def get_iis_site_info(optionalData=""):
    print(json.dumps(panelSite().get_site_info(
        siteName=optionalData["siteName"]), ensure_ascii=False))


# 获取Python的可执行路径
def get_python_execute_path(optionalData=""):
    print(optionalData['en'])
    print(sys.executable)


# DIC转转对象
class DictObj(object):
    def __init__(self, map):
        self.map = map

    def __setattr__(self, name, value):
        if name == 'map':
            object.__setattr__(self, name, value)
            return
        self.map[name] = value

    def __getattr__(self, name):
        v = self.map[name]
        if isinstance(v, (dict)):
            return DictObj(v)
        if isinstance(v, (list)):
            r = []
            for i in v:
                r.append(DictObj(i))
            return r
        else:
            return self.map[name]

    def __getitem__(self, name):
        return self.map[name]


if __name__ == '__main__':
    try:
        ops, args = getopt.getopt(sys.argv[1:], "f:d:")
        data = ""
        function = ""
        for opt, arg in ops:
            if(opt == "-f"):
                function = arg
            elif(opt == '-d'):
                data = arg
        if function and callable(eval(function)):
            eval(str(function))(json.loads(data))
        else:
            print("Not a Valid Callable method: "+function)
    except getopt.GetoptError:
        print('PhpUtils.py -f <FunctionName> -d <JsonEncodedDataString>')
        sys.exit(2)
