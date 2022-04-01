<?php

namespace TokenSSL\Common;

class StreamForwardingUtils
{
    const STREAM_FORWARD_V1 = 'include /www/server/panel/plugin/tokenssl/conf/stream.conf;';
    const NGINX_CONF_V1 = '/www/server/nginx/conf/nginx.conf';

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
        $fp = fopen(self::NGINX_CONF_V1, 'r');
        $content = fread($fp, filesize(self::NGINX_CONF_V1));
        fclose($fp);

        if (!preg_match('/stream[\W]+?{/', $content)) {
            if (strpos($content, '/www/server/panel/vhost/nginx/tcp/') === false) {
                @mkdir('/www/server/panel/vhost/nginx/tcp/', 0755, true);
            }

            $fp = fopen(self::NGINX_CONF_V1, 'w');
            $content = preg_replace('/events[\W]+?{/', "stream {\n\tlog_format tcp_format '\$time_local|\$remote_addr|\$protocol|\$status|\$bytes_sent|\$bytes_received|\$session_time|\$upstream_addr|\$upstream_bytes_sent|\$upstream_bytes_received|\$upstream_connect_time';\n\n\taccess_log /www/wwwlogs/tcp-access.log tcp_format;\n\terror_log /www/wwwlogs/tcp-error.log;\n\tinclude /www/server/panel/vhost/nginx/tcp/*.conf;\n}\n\nevents\n\t{", $content, 1);
            fwrite($fp, PHP_EOL . self::STREAM_FORWARD_V1 . PHP_EOL);
            fclose($fp);
        }
    }
}
