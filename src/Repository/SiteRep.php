<?php

namespace TokenSSL\Repository;

use blobfolio\domain\domain;
use TokenSSL\Common\DatabaseUtils;
use TokenSSL\Common\NginxVhostUtils;

class SiteRep
{
    /**
     * 获取系统中所有站
     * @param $draw
     * @param int $offset
     * @param int $limit
     * @param null $nameSearchValue
     * @return array|string
     */
    public static function getSiteList($draw, $offset = 0, $limit = 6, $nameSearchValue = NULL)
    {
        $db = DatabaseUtils::initBaoTaSystemDatabase();
        $dbLocal = DatabaseUtils::initLocalDatabase();
        try {
            if ($nameSearchValue !== NULL) {
                $sites = $db->query('SELECT * FROM sites where sites.name LIKE ? order by sites.id desc limit ? offset ? ', "%$nameSearchValue%", $limit, $offset)->fetchAll();
                $fetch = $db->query('select count(id) as total from sites where sites.name LIKE ?', "%$nameSearchValue%")->fetch();
                $recordsFiltered = $fetch['total'];
            } else {
                $sites = $db->query('SELECT * FROM sites order by sites.id desc limit ? offset ? ', $limit, $offset)->fetchAll();
                $fetch = $db->query('select count(id) as total from sites')->fetch();
                $recordsFiltered = $fetch['total'];
            }
            $fetch = $db->query('select count(id) as total from sites')->fetch();
            $recordsTotal = $fetch['total'];
        } catch (\Exception $exception) {
            return $exception->getMessage();
        }
        $sites = json_decode(json_encode($sites), 1);
        // 查询证书配置信息
        foreach ($sites as $key => $site) {
            $vhostInfo = NginxVhostUtils::getVhostConfigInfo($site['name']);
            $sites[$key]['vhost_info'] = $vhostInfo;
            $sites[$key]['ssl_order'] = $dbLocal->query('select `id`,`status` from certificate where `site_id`=?', ($site['id']))->fetch();
        }

        return array(
            'data' => $sites,
            'draw' => $draw,
            "recordsTotal" => $recordsTotal,
            "recordsFiltered" => $recordsFiltered
        );
    }

    /**
     * 获取站点的合法域名信息
     * @param $siteName
     * @return array
     */
    public static function getValidDomains($siteName)
    {
        $siteInfo = self::getSiteInfo($siteName);
        $siteDomains = $siteInfo['site_domains'];
        $validDomains = [];
        $fqdn = 0;
        $wildcard = 0;
        $ipv4 = 0;
        foreach ($siteDomains as $key => $domain) {
            if (
                !!filter_var($domain, \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV4) === TRUE
                && !!filter_var($domain, \FILTER_VALIDATE_IP, \FILTER_FLAG_NO_PRIV_RANGE === TRUE)
            ) {
                // 公網IP地址
                $validDomains[] = $domain;
                $ipv4 += 1;
            } elseif (!!filter_var($domain, \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV6) === FALSE) {
                // 通配符去除*.
                $Dwildcard = false;
                if (strpos($domain, '*.') !== FALSE) {
                    $domain = str_replace('*.', '', $domain);
                    $Dwildcard = true;
                }
                $DAttr =  new domain(trim($domain));
                // 检查是可靠的 FQDN 域名
                if ($DAttr->is_valid() === TRUE) {
                    $realD = $DAttr->get_host();
                    if ($Dwildcard === true) {
                        $realD = '*.' . $realD;
                        $wildcard += 1;
                    } else {
                        $fqdn += 1;
                    }
                    $validDomains[] = $realD;
                }
            }
        }

        return array(
            'domains' => $validDomains,
            'fqdn' => $fqdn,
            'ipv4' => $ipv4,
            'wildcard' => $wildcard
        );
    }

    /**
     * 查找可用的域名信息
     * @param $siteDomains
     * @return array
     */
    protected static function findValidDomains($siteDomains)
    {
        $validDomains = [];
        $fqdn = 0;
        $wildcard = 0;
        $ipv4 = 0;
        foreach ($siteDomains as $key => $domain) {
            if (
                !!filter_var($domain, \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV4) === TRUE
                && !!filter_var($domain, \FILTER_VALIDATE_IP, \FILTER_FLAG_NO_PRIV_RANGE === TRUE)
            ) {
                // 公網IP地址
                $validDomains[] = $domain;
                $ipv4 += 1;
            } elseif (!!filter_var($domain, \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV6) === FALSE) {
                // 通配符去除*.
                $Dwildcard = false;
                if (strpos($domain, '*.') !== FALSE) {
                    $domain = str_replace('*.', '', $domain);
                    $Dwildcard = true;
                }
                $DAttr =  new domain(trim($domain));
                // 检查是可靠的 FQDN 域名
                if ($DAttr->is_valid() === TRUE) {
                    $realD = $DAttr->get_host();
                    if ($Dwildcard === true) {
                        $realD = '*.' . $realD;
                        $wildcard += 1;
                    } else {
                        $fqdn += 1;
                    }
                    $validDomains[] = $realD;
                }
            }
        }

        return array(
            'domains' => $validDomains,
            'fqdn' => $fqdn,
            'ipv4' => $ipv4,
            'wildcard' => $wildcard
        );
    }

    /**
     * 获取单个站点信息
     * @param int $siteId
     * @return mixed|string
     */
    public static function getSiteById($siteId)
    {
        try {
            $db = DatabaseUtils::initBaoTaSystemDatabase();
            $site = json_decode(json_encode($db->query('SELECT * FROM sites where sites.id = ?', $siteId)->fetch()), 1);
            if (!$site) {
                return false;
            }
            $siteName = $site['name'];
            $site['vhost_info'] = NginxVhostUtils::getVhostConfigInfo($siteName);
            // 站点绑定的域名
            $siteDomains = [];
            foreach (json_decode(json_encode($db->query('select `name` from domain where pid = ?', $site['id'])->fetchAll()), 1) as $key => $siteD) {
                $siteDomains[] = $siteD['name'];
            }
            $site['site_domains'] = $siteDomains;
            $site['valid_domains'] = self::findValidDomains($siteDomains);
            // 证书内的域名
            $certSAN =  explode(',', str_replace(" ", "", str_replace("DNS:", "", $site['vhost_info']['cert_info']['extensions']['subjectAltName'])));
            // 去除IP地址前缀
            foreach ($certSAN as $key => $name) {
                if (strpos($name, 'IPAddress:') !== FALSE) {
                    $certSAN[$key] = str_replace('IPAddress:', '', $name);
                }
            }
            $site['cert_domains'] = $certSAN;
            // 受保护的域名
            $site['secure_domains'] = [];
            $site['insecure_domains'] = [];
            // 统计用于申请证书的域名SAN，已经适配过通配符了
            $site['valid_cert_domains'] = [];
            // 统计用于申请证书的域名类型数量
            $site['valid_cert_domains_count'] = [
                'fqdn' => 0,
                'wildcard' => 0,
                'ipv4' => 0,
            ];
            foreach ($site['valid_domains']['domains'] as $key => $name) {
                if (strpos($name, '*.') !== FALSE) {
                    $site['valid_cert_domains_count']['wildcard'] += 1;
                    $site['valid_cert_domains'][] = $name;
                } else {
                    $domain = new domain($name);
                    if ($domain->is_ip() === TRUE) {
                        $site['valid_cert_domains_count']['ipv4'] += 1;
                        $site['valid_cert_domains'][] = $name;
                    } elseif ($domain->is_valid()) {
                        $site['valid_cert_domains_count']['fqdn'] += 1;
                        $site['valid_cert_domains'][] = $name;
                        // 检查是否已经存在通配符域名与此兼容
                        // $nameSt = explode('.', $name);
                        // unset($nameSt[0]); // 暂时关闭了通配符适配， 因用户配置可能会出现通配符匹配混乱
                        // if(in_array("*.".implode('.', $nameSt),$site['valid_domains']['domains']) === FALSE){
                        //     $site['valid_cert_domains_count']['fqdn'] += 1;
                        //     $site['valid_cert_domains'][] = $name;
                        // }
                    }
                }
            }

            // 检查域名是否受到保护, 特地需要检查通配符域名
            foreach ($site['valid_domains']['domains'] as $key => $domain) {
                $ftdDomain = trim(strtolower($domain));
                if (in_array($ftdDomain, $certSAN)) {
                    $site['secure_domains'][] = $ftdDomain;
                } else {
                    $site['insecure_domains'][] = $ftdDomain;
                }
            }
            // 检查证书中的通配符域名是否可以保护部分insecure_domains
            foreach ($certSAN as $key => $domainName) {
                $certSan = trim(strtolower($domainName));
                if (strpos($certSan, '*') !== false) {
                    // todo:: 处理通配符域名适配
                    foreach ($site['insecure_domains'] as $key2 => $siteDomain) {
                        if (self::wildcardDomainRuleFetch($certSan, $siteDomain) === true) {
                            $site['secure_domains'][] = $siteDomain;
                            unset($site['insecure_domains'][$key2]);
                        }
                    }
                }
            }
            return $site;
        } catch (\Exception $exception) {
            return $exception->getMessage();
        }
    }

    /**
     * 获取单个站点信息
     * @param $siteName
     * @return mixed|string
     */
    public static function getSiteInfo($siteName)
    {
        try {
            $db = DatabaseUtils::initBaoTaSystemDatabase();
            $site = json_decode(json_encode($db->query('SELECT * FROM sites where sites.name = ?', $siteName)->fetch()), 1);
            $site['vhost_info'] = NginxVhostUtils::getVhostConfigInfo($siteName);
            // 站点绑定的域名
            $siteDomains = [];
            foreach (json_decode(json_encode($db->query('select `name` from domain where pid = ?', $site['id'])->fetchAll()), 1) as $key => $siteD) {
                $siteDomains[] = $siteD['name'];
            }
            $site['site_domains'] = $siteDomains;
            $site['valid_domains'] = self::findValidDomains($siteDomains);
            // 证书内的域名
            $certSAN =  explode(',', str_replace(" ", "", str_replace("DNS:", "", $site['vhost_info']['cert_info']['extensions']['subjectAltName'])));
            // 去除IP地址前缀
            foreach ($certSAN as $key => $name) {
                if (strpos($name, 'IPAddress:') !== FALSE) {
                    $certSAN[$key] = str_replace('IPAddress:', '', $name);
                }
            }
            $site['cert_domains'] = $certSAN;
            // 受保护的域名
            $site['secure_domains'] = [];
            $site['insecure_domains'] = [];
            // 统计用于申请证书的域名SAN，已经适配过通配符了
            $site['valid_cert_domains'] = [];
            // 统计用于申请证书的域名类型数量
            $site['valid_cert_domains_count'] = [
                'fqdn' => 0,
                'wildcard' => 0,
                'ipv4' => 0,
            ];
            foreach ($site['valid_domains']['domains'] as $key => $name) {
                if (strpos($name, '*.') !== FALSE) {
                    $site['valid_cert_domains_count']['wildcard'] += 1;
                    $site['valid_cert_domains'][] = $name;
                } else {
                    $domain = new domain($name);
                    if ($domain->is_ip() === TRUE) {
                        $site['valid_cert_domains_count']['ipv4'] += 1;
                        $site['valid_cert_domains'][] = $name;
                    } elseif ($domain->is_valid()) {
                        $site['valid_cert_domains_count']['fqdn'] += 1;
                        $site['valid_cert_domains'][] = $name;
                        // 检查是否已经存在通配符域名与此兼容
                        // $nameSt = explode('.', $name);
                        // unset($nameSt[0]); // 暂时关闭了通配符适配， 因用户配置可能会出现通配符匹配混乱
                        // if(in_array("*.".implode('.', $nameSt),$site['valid_domains']['domains']) === FALSE){
                        //     $site['valid_cert_domains_count']['fqdn'] += 1;
                        //     $site['valid_cert_domains'][] = $name;
                        // }
                    }
                }
            }

            // 检查域名是否受到保护, 特地需要检查通配符域名
            foreach ($site['valid_domains']['domains'] as $key => $domain) {
                $ftdDomain = trim(strtolower($domain));
                if (in_array($ftdDomain, $certSAN)) {
                    $site['secure_domains'][] = $ftdDomain;
                } else {
                    $site['insecure_domains'][] = $ftdDomain;
                }
            }
            // 检查证书中的通配符域名是否可以保护部分insecure_domains
            foreach ($certSAN as $key => $domainName) {
                $certSan = trim(strtolower($domainName));
                if (strpos($certSan, '*') !== false) {
                    // todo:: 处理通配符域名适配
                    foreach ($site['insecure_domains'] as $key2 => $siteDomain) {
                        if (self::wildcardDomainRuleFetch($certSan, $siteDomain) === true) {
                            $site['secure_domains'][] = $siteDomain;
                            unset($site['insecure_domains'][$key2]);
                        }
                    }
                }
            }
            return $site;
        } catch (\Exception $exception) {
            return $exception->getMessage();
        }
    }

    /**
     * 进行通配符规则的域名适配
     * @param $wildcardDomainName
     * @param $FQDN
     * @return bool
     */
    private static function wildcardDomainRuleFetch($wildcardDomainName, $FQDN)
    {
        $wildcardDomainName = trim(strtolower($wildcardDomainName));
        $originalDomainName = trim(strtolower($FQDN));
        if (strpos($wildcardDomainName, '*') === false) {
            return false;
        } else {
            $orginalDomainChilds = explode(".", $originalDomainName);
            array_shift($orginalDomainChilds);
            $originDomainSuffix = implode('.', $orginalDomainChilds);
            $wildcardDomainSuffix = str_replace("*.", "", $wildcardDomainName);
            if (strpos(str_replace($wildcardDomainSuffix, "", $originDomainSuffix), ".") !== FALSE) {
                return false;
            } else {
                return true;
            }
        }
    }
}
