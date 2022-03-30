#!/bin/bash
PATH=/www/server/panel/pyenv/bin:/bin:/sbin:/usr/bin:/usr/sbin:/usr/local/bin:/usr/local/sbin:~/bin
export PATH
#配置插件安装目录, 目前测试为该脚本当前执行目录
install_path="/www/server/panel/plugin/tokenssl"
setup_path="${install_path}/../../../"
#安装
Install()
{
	echo '正在安装...'
	#==================================================================
	#手动准备静态文件 (某些版本中测试到 icon 不展示, 暂未查明是否为宝塔安装plugin时复制错误)
	#echo '准备静态文件...'
	#cp -f "${install_path}/icon.png" "${setup_path}/panel/BTPanel/static/img/soft_ico/ico-tokenssl.png"
	#echo '检查并恢复备份的数据库文件...'
	${setup_path}panel/pyenv/bin/python ${install_path}/install.py install
	#if [ -f "${install_path}/../../data/plugin_tokenssl_backup.db" ]; then
	#  cp -f "${install_path}/../../data/plugin_tokenssl_backup.db" "${install_path}/databases/main.db"
  #fi
	#依赖安装开始
  #echo "可能需要安装一些依赖文件..."
	#依赖安装结束
	#==================================================================

	echo '================================================'
	echo '安装完成'
}

#卸载
Uninstall()
{
  echo '执行删除前操作...'
  ${setup_path}panel/pyenv/bin/python ${install_path}/install.py uninstall
  echo '删除插件目录...'
	rm -rf $install_path
}

#操作判断
if [ "${1}" == 'install' ];then
	Install
elif [ "${1}" == 'uninstall' ];then
	Uninstall
else
	echo 'Error!';
fi