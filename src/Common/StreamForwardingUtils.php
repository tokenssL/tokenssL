<?php

namespace TokenSSL\Common;

class StreamForwardingUtils
{
    const STREAM_FORWARD_V1 = 'include /www/server/panel/plugin/tokenssl/conf/stream.conf;';
    const NGINX_CONF = '/www/server/nginx/conf/nginx.conf';

    public static function installStreamForwarding()
    {
        self::installStreamForNginx();
        if (!file_exists('/www/server/panel/vhost/nginx/tcp/tokenssl.conf')) {
            copy('/www/server/panel/plugin/tokenssl/conf/tokenssl.conf', '/www/server/panel/vhost/nginx/tcp/tokenssl.conf');
        }
    }

    public static function uninstallStreamForwarding()
    {
        if (file_exists('/www/server/panel/vhost/nginx/tcp/tokenssl.conf')) {
            @unlink('/www/server/panel/vhost/nginx/tcp/tokenssl.conf');
        }
    }

    public static function installStreamForNginx()
    {
        $fp = fopen(self::NGINX_CONF, 'r');
        $content = fread($fp, filesize(self::NGINX_CONF));
        fclose($fp);

        if (!preg_match('/stream[\W\{]+?{/', $content)) {
            if (strpos($content, '/www/server/panel/vhost/nginx/tcp/') === false) {
                try {
                    @mkdir('/www/server/panel/vhost/nginx/tcp/', 0755, true);
                } catch (\Exception $e) {
                }
            }

            if (!file_exists(self::NGINX_CONF . '.backup')) {
                @copy(self::NGINX_CONF, self::NGINX_CONF . '.backup');
            }

            $fp = fopen(self::NGINX_CONF, 'w');
            $content = preg_replace('/events[\W\{]+?{/', "stream {\n\tlog_format tcp_format '\$time_local|\$remote_addr|\$protocol|\$status|\$bytes_sent|\$bytes_received|\$session_time|\$upstream_addr|\$upstream_bytes_sent|\$upstream_bytes_received|\$upstream_connect_time';\n\n\taccess_log /www/wwwlogs/tcp-access.log tcp_format;\n\terror_log /www/wwwlogs/tcp-error.log;\n\tinclude /www/server/panel/vhost/nginx/tcp/*.conf;\n}\n\nevents\n\t{", $content, 1);
            fwrite($fp, $content);
            fclose($fp);
        }
    }
}
