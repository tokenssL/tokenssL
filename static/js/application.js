//插件唯一识别ID
let plugin_id = "tokenssl";

//定义窗口尺寸
$('.layui-layer-page').css({ 'width': '900px' });

//左测菜单切换效果
$(".bt-w-menu p").click(function () {
    $(this).addClass('bgw').siblings().removeClass('bgw')
});

/**
 * 插件交互对象
 * 您的所有JS代码可以写在里面
 * 若不习惯JS的面向对象编程，可删除此对象，使用传统函数化的方式编写
 * */
var tokenssl = {
    //构造概览内容
    // get_index: function () {
    //     $('.plugin_body').html("<h1 style='text-align:center;margin-top:30%;'>这是一个示例插件!</h1>");
    // },
    gVars: {
        reg: {},
        siteSetting: {},
        payment: {},
    },
    /**
     * 获取PHPINFO
     */
    phpinfo: function (p) {
        if (p == undefined) p = 1;
        request_plugin('tokenssl', 'phpinfo', { p: p, callback: 'demo.phpinfo' }, function (rdata) {
            $('.plugin_body').html("<pre>" + rdata + "</pre>");
        });
    },
    // 页面主内容导航
    page: function (page, params = {}, loadingText = false) {
        if (loadingText !== false) {
            var loads = bt.load(loadingText + '...');
        }

        request_plugin(plugin_id, page, params, function (response) {
            if (loadingText !== false) {
                loads.close();
            }
            if (undefined !== response.status && response.status === false) {
                layer.msg(response.msg, { icon: 2 });
            } else if (response !== "") {
                $('.plugin_body').html(response);
            } else {
                layer.msg("程序启动失败", { icon: 2 });
                $('.plugin_body').html('<p style="color: darkred;margin-top: 15px;margin-bottom: 10px;">抱歉！TokenSSL™ 程序启动失败, 请检查您的最新版 PHP 环境是否设置正确。或查看我们的产品社区获取帮助。</p><a href="https://help.tokenssl.com" target="_blank" class="btn btn-xs btn-primary">获取帮助</a>');
            }
        });
    },
    checkMxStatus: function (data) {
        if (data.success) {
            if ($('#validating-mx-status').length) {
                $('#validating-mx-status').text('(您只需等待即可)');
            }
            if ($('#mx-not-open').length) {
                $('#mx-not-open').hide();
            }
        } else {
            if ($('#validating-mx-status').length) {
                $('#validating-mx-status').text('(无法自动签发通配符，请验证DNS，或开通25端口)');
            }
            if ($('#mx-not-open').length) {
                $('#mx-not-open').show();
            }
        }
    },
    checkToken: function (siteId, token) {
        // 检查Org
        var loads = bt.load('正在检查 Token...');
        request_plugin('tokenssl', 'checkToken', { siteId: siteId, token: token }, function (response) {
            loads.close();
            if (response.status !== "success") {
                layer.msg(response.message, { icon: 2 });
            } else {
                layer.msg(response.message, { icon: 1 });
                tokenssl.page('siteSSLdeploy', { siteId: siteId, token: token, unique_value: response.unique_value, period: response.period });
            }
        });
    },
    createNewFullSSL: function (siteId, token, unique_value) {
        // 检查Org
        var loads = bt.load('正在提交签名请求...');
        request_plugin('tokenssl', 'createNewFullSSL', { siteId: siteId, token: token, unique_value }, function (response) {
            loads.close();
            if (response.status !== "success") {
                layer.msg(response.message, { icon: 2 });
            } else {
                layer.msg(response.message, { icon: 1 });
                tokenssl.page('siteSetting', { siteId: siteId });
            }
        });
    },
    refreshOrder: function (siteId, status) {
        request_plugin('tokenssl', 'refreshOrder', { siteId: siteId }, function (response) {
            if (response.status == "success") {
                if (response.cert.status == status) {
                    tokenssl.page('siteSetting', { siteId: siteId });
                    return;
                }
            }
            setTimeout(function () {
                tokenssl.refreshOrder(siteId, status); 
            }, 3000);
        });
    },
    setBaotaSSL: function (certPem, privateKey) {
        var loads = bt.load('启用中，请稍等...');
        request_baotaAjax('config', 'SavePanelSSL', { privateKey: privateKey, certPem: certPem }, function (response) {
            if (response.status !== true) {
                layer.msg(response.msg, { icon: 2 });
            } else {
                request_baotaAjax('config', 'SetPanelSSL', { cert_type: 1 }, function (response) {
                    if (response.status !== true) {
                        layer.msg(response.msg, { icon: 2 });
                    } else {
                        request_baotaAjax('system', 'ReWeb', { }, function (response) {
                            if (response.status !== true) {
                                layer.msg(response.msg, { icon: 2 });
                            } else {
                                layer.msg('已成功为宝塔启用面板SSL证书，刷新中', { icon: 1 });
                                setTimeout(function () {
                                    location.href = location.href.replace(/^http:\/\//, 'https://');
                                }, 3000);
                            }
                        }, 10000, 'GET');
                    }
                });
            }
        });
    },
    setSSL: function (siteName, certCode, keycode) {
        var loads = bt.load('正在尝试安装...');
        request_baotaAjax('site', 'SetSSL', { type: 1, siteName: siteName, key: keycode, csr: certCode }, function (response) {
            loads.close();
            if (response.status !== true) {
                layer.msg(response.msg, { icon: 2 });
            } else {
                layer.msg(response.msg, { icon: 1 });
                tokenssl.page('siteSetting', { siteId: siteId });
            }
        });
    },
    reissueSSLOrder: function (siteId) {
        var loads = bt.load('正在提交签名请求...');
        request_plugin('tokenssl', 'reissueSSLOrder', { siteId: siteId }, function (response) {
            loads.close();
            if (response.status !== "success") {
                layer.msg(response.message, { icon: 2 });
            } else {
                layer.msg(response.message, { icon: 6 });
                tokenssl.page('siteSetting', { siteId: siteId });
            }
        });
    },
    removeSSLOrder: function (siteId) {
        layer.confirm("确认删除此订单？取消订单后您可以重新为此站点配置申请新的SSL证书", { title: "删除证书订单", icon: 0 }, function (t) {
            request_plugin('tokenssl', 'removeSSLOrder', { siteId: siteId }, function (response) {
                if (response.status !== "success") {
                    layer.msg(response.message, { icon: 2 });
                    return false;
                } else {
                    layer.msg(response.message, { icon: 6 });
                    tokenssl.page('siteSetting', { siteId: siteId });
                }
            });
        });
    },
    recheckDomainValidation: function (siteId) {
        layer.confirm("确认域名验证信息配置正确后, 执行此操作通知签发机构重新验证域名", { title: "重新验证", icon: 0 }, function (t) {
            request_plugin('tokenssl', 'recheckDomainValidation', { siteId: siteId }, function (response) {
                if (response.status !== "success") {
                    layer.msg(response.message, { icon: 2 });
                    return false;
                } else {
                    layer.msg("提交完成, 颁发机构可能需要5分钟完成", { icon: 6 });
                    tokenssl.page('siteSetting', { siteId: siteId });
                }
            });
        });
    },
    toggleAutoRenewal: function (siteId) {
        request_plugin('tokenssl', 'toggleAutoRenewal', { siteId: siteId }, function (response) {
            if (response.status !== "success") {
                layer.msg(response.message, { icon: 2 });
                return false;
            } else {
                layer.msg(response.message, { icon: 6 });
            }
        });
    },
    checkSSLOrderStatus: function (siteId, refJob) {
        request_plugin('tokenssl', 'checkSSLOrderStatus', { siteId: siteId }, function (response) {
            if (response.status === "success") {
                layer.msg("证书已成功签发", { icon: 1 });
                // clearInterval(refJob); // 清除定时刷新任务
                tokenssl.page('siteSetting', { siteId: siteId });
            } else {
                layer.msg("还未签发, 请检查域名验证信息是否设置正确", { icon: 5 });
            }
        });
    },
    /**
     * 检查客户端版本更新
     * @param refJob
     */
    checkClientVersion: function (refJob) {
        request_plugin('tokenssl', 'checkUpdateVersion', {}, function (response) {
            if (null !== response && response.status === "success") {
                refJob(response);
            } else {
                // layer.msg("检查版本更新失败, 您可尝试访问Github查看最新版本", {icon: 5});
            }
        });
    },
    openPath: function (a) {
        setCookie("Path", a);
        window.open("/files", '_blank');
    },
};

/**
 * 发送请求到插件
 * 注意：除非你知道如何自己构造正确访问插件的ajax，否则建议您使用此方法与后端进行通信
 * @param plugin_name    插件名称 如：demo
 * @param function_name  要访问的方法名，如：get_logs
 * @param args           传到插件方法中的参数 请传入数组，示例：{p:1,rows:10,callback:"demo.get_logs"}
 * @param callback       请传入处理函数，响应内容将传入到第一个参数中
 */
function request_plugin(plugin_name, function_name, args, callback, timeout) {
    if (!timeout) timeout = 10000;
    $.ajax({
        type: 'POST',
        url: '/plugin?action=a&s=' + function_name + '&name=' + plugin_name,
        data: args,
        timeout: timeout,
        success: function (rdata) {
            if (!callback) {
                layer.msg(rdata.msg, { icon: rdata.status ? 1 : 2 });
                return;
            }
            return callback(rdata);
        },
        error: function (ex) {
            if (!callback) {
                layer.msg('请求过程发现错误!', { icon: 2 });
                return;
            }
            return callback(ex);
        }
    });
}

/**
 * 发送 Ajax 请求到宝塔面板
 * @param layer
 * @param action
 * @param args
 * @param callback
 * @param timeout
 */
function request_baotaAjax(layer, action, args, callback, timeout, type) {
    if (!timeout) timeout = 10000;
    if (!type || 'undefined' === typeof type) {
        type = 'POST';
    }
    $.ajax({
        type: type,
        url: '/' + layer + '?action=' + action,
        data: args,
        timeout: timeout,
        success: function (rdata) {
            if (!callback) {
                layer.msg(rdata.msg, { icon: rdata.status ? 1 : 2 });
                return;
            }
            return callback(rdata);
        },
        error: function (ex) {
            if (!callback) {
                layer.msg('请求过程发现错误!', { icon: 2 });
                return;
            }
            return callback(ex);
        }
    });
}