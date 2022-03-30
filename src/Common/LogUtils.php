<?php

namespace TokenSSL\Common;

class LogUtils
{
    const STATUS_SUCCESS = "success";
    const STATUS_ERROR = "error";
    const TITLE_CERT_CREATED = "cert_created";
    const TITLE_CERT_UPGRADE = "cert_upgrade";
    const TITLE_CERT_REISSUE = "cert_reissue";

    /**
     * 写客户端操作日志
     * @param $status
     * @param $title
     * @param $description
     * @param int $certificate_id
     */
    public static function writeLog($status, $title, $description, $certificate_id = -1)
    {
        $db = DatabaseUtils::initLocalDatabase();
        $db->query('insert into logs ?', [
            'status' => $status,
            'title' => $title,
            'description' => $description,
            'certificate_id' => $certificate_id,
            'created_at' => date('Y-m-d H:i:s', time())
        ]);
    }

    /**
     * 获取列表
     * @param $draw
     * @param int $offset
     * @param int $limit
     * @param null $nameSearchValue
     * @return array|string
     * @throws \TokenSSL\TokenSSLException
     */
    public static function getClientLogList($draw, $offset = 0, $limit = 6, $nameSearchValue = NULL)
    {
        $db = DatabaseUtils::initLocalDatabase();
        try {
            if ($nameSearchValue !== NULL) {
                $sites = $db->query('SELECT * FROM logs where logs.description LIKE ? order by logs.id desc limit ? offset ? ', "%$nameSearchValue%", $limit, $offset)->fetchAll();
                $fetch = $db->query('select count(id) as total from logs where logs.description LIKE ?', "%$nameSearchValue%")->fetch();
                $recordsFiltered = $fetch['total'];
            } else {
                $sites = $db->query('SELECT * FROM logs order by logs.id desc limit ? offset ? ', $limit, $offset)->fetchAll();
                $fetch = $db->query('select count(id) as total from logs')->fetch();
                $recordsFiltered = $fetch['total'];
            }
            $fetch = $db->query('select count(id) as total from logs')->fetch();
            $recordsTotal = $fetch['total'];
        } catch (\Exception $exception) {
            return $exception->getMessage();
        }
        $sites = json_decode(json_encode($sites), 1);
        return array(
            'data' => $sites,
            'draw' => $draw,
            "recordsTotal" => $recordsTotal,
            "recordsFiltered" => $recordsFiltered
        );
    }
}
