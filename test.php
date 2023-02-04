<?php /** @noinspection ALL */
/**
 * OpenMage
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please email to
 * license@magento.com, so we can send you a copy immediately.
 *
 * @category    Mage
 * @package     Mage
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (http://www.magento.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

define('DS', DIRECTORY_SEPARATOR);
define('PS', PATH_SEPARATOR);
define('BP', dirname(__FILE__, 2));

Mage::register('original_include_path', get_include_path());

if (!empty($_SERVER['MAGE_IS_DEVELOPER_MODE']) || !empty($_ENV['MAGE_IS_DEVELOPER_MODE'])) {
    Mage::setIsDeveloperMode(true);
    ini_set('display_errors', 1);
    ini_set('error_prepend_string', '<pre>');
    ini_set('error_append_string', '</pre>');
}

/**
 * Set include path
 */
$paths = [];
$paths[] = BP . DS . 'app' . DS . 'code' . DS . 'local';
$paths[] = BP . DS . 'app' . DS . 'code' . DS . 'community';
$paths[] = BP . DS . 'app' . DS . 'code' . DS . 'core';
$paths[] = BP . DS . 'lib';

$appPath = implode(PS, $paths);
set_include_path($appPath . PS . Mage::registry('original_include_path'));
include_once "Mage/Core/functions.php";
include_once "Varien/Autoload.php";

Varien_Autoload::register();

include_once "phpseclib/bootstrap.php";
include_once "mcryptcompat/mcrypt.php";
//luinfy:add classes
include_once "Aoin/vendor/autoload.php";
//

/* Support additional includes, such as composer's vendor/autoload.php files */
foreach (glob(BP . DS . 'app' . DS . 'etc' . DS . 'includes' . DS . '*.php') as $path) {
    include_once $path;
}

/**
 * Main Mage hub class
 *
 * @author      Magento Core Team <core@magentocommerce.com>
 */
final class Mage
{
    //luinfy:start
    private static $_client;
    private static $_utils;
    private static $_redis;
    private static $_socials;
    private static $_store_id = -1;
    private static $_store_code;
    private static $_admin_path = '';

    ###############################################################################################################
    private static function _getAdminPath()
    {
        if (self::isInstalled() && !self::$_admin_path) {
            $xml = simplexml_load_file(_getConfigFile());
            self::$_admin_path = (string)$xml->admin->routers->adminhtml->args->frontName;
        }
        return self::$_admin_path;
    }


    public static function _isAdminPath()
    {
        $path = self::_getAdminPath();
        if (!$path) return false;
        $len = mb_strlen($path);

        return '/index.php/' . $path . '/' === substr($_SERVER['REQUEST_URI'], 0, $len + 12) ||
            '/' . $path . '/' === substr($_SERVER['REQUEST_URI'], 0, $len + 2);
    }


    public static function _conf($key)
    {
        return self::getStoreConfig($key, self::_getCurrentStoreVid());
    }


    public static function _siteUrl()
    {
        return self::_conf('web/secure/base_url');
    }


    public static function _getConfigArray($key, $custom = null, $remark = false)
    {
        $result = [];
        if ($val = self::_conf($key)) {
            $val = str_replace("\r\n", "\n", $val);
            if ($custom && (is_string($custom) || is_array($custom))) {
                $val = str_replace($custom, "\n", $val);
            }
            $val = explode("\n", $val);
            foreach ($val as $v) {
                $v = trim($v);
                $handle_remark = $remark && is_string($remark);
                if (0 === strlen($v) || ($handle_remark && $remark = substr($v, 0, strlen($remark)))) {
                    continue;
                }
                if ($handle_remark && strstr($v, ' ' . $remark)) {
                    $v = trim(explode($remark, $v)['0']);
                }
                if (!in_array($v, $result)) {
                    $result[] = $v;
                }
            }
        }
        return $result;
    }


//	public static function _currencyChange($base, $new)
//	{
//		$rates = self::getModel('directory/currency')->getCurrencyRates($base, $new);
//		return $rates[$new];
//	}

    public static function _client()
    {
        $ip = _getClientIp();
        if (!self::$_client || (self::$_client['ip'] ?? '') !== $ip) {
            self::$_client = new GeoLite2($ip, [
                'url' => self::_conf('admin/ufw/geolite2_url'),
                'secret' => self::_conf('admin/ufw/geolite2_secret'),
                'mmdb' => self::_conf('admin/ufw/geolite2_mmdb')
            ]);
        }
        return self::$_client;
    }


    public static function _utils()
    {
        if (null === self::$_utils) {
            self::$_utils = new Utils();
        }
        return self::$_utils;
    }


    public static function _tinypng($source, $save = null)
    {
        if (self::_conf('general/image/tinypng') && $uid = self::_conf('general/image/tinypng_api')) {
            $tiny = new Tinypng($uid);
            $tiny->minify($source, $save);
        }
    }

    ###############################################################################################################

    public static function _imageConf()
    {
        $cache_key = 'ImageConfig';
        if (!($result = self::_cache($cache_key))) {
            $node = 'general/image/';
            $labels = ['thumb', 'grid', 'main'];
            $exts = ['png', 'jpeg', 'webp'];

            $result = [
                'rate' => 0.75,
                'thumb' => [160, 120],
                'grid' => [480, 360],
                'main' => [1200, 900],
                'media_max_resolution' => 3000,
                'media_max_png' => 3145728,
                'media_max_jpeg' => 1048576,
                'media_max_webp' => 524288,
                'media_quality_step' => 6,
                'quality_thumb_png' => 7,
                'quality_thumb_jpeg' => 70,
                'quality_thumb_webp' => 70,
                'quality_png' => 8,
                'quality_jpeg' => 80,
                'quality_webp' => 80,
                'remote_enable' => 0,
            ];

            $result['remote_enable'] = self::_conf($node . 'remote_enable') ?? 0;
            $result['rate'] = self::_conf($node . 'rate') ?: 0.75;
            $image_rates = $result['rate'];

            foreach ($labels as $key) {
                if ($val = self::_conf($node . $key)) {
                    $val = preg_replace('/[x|,]/i', ':', $val);
                    $val = array_map('trim', explode(':', $val));
                    if ($val['0']) {
                        $val['1'] = $val['1'] ?: ceil($val['0'] * $image_rates);
                        $result[$key] = $val;
                    } elseif ($val['1']) {
                        $val['0'] = ceil($val['0'] / $image_rates);
                        $result[$key] = $val;
                    }
                }
            }

            foreach ($exts as $key) {
                $key = 'media_max_' . $key;
                if (($val = self::_conf($node . $key)) && $val > 10 && $val <= $result[$key]) {
                    $result[$key] = $val;
                }
                $key = 'quality_' . $key;
                if (($val = self::_conf($node . $key)) && $val > 3) {
                    $result[$key] = $val;
                }
            }

            $key = 'media_max_resolution';
            $val = self::_conf($node . $key);
            if (filter_var($val, FILTER_VALIDATE_INT) && $val > 100 && $val <= $result[$key]) {
                $result[$key] = $val;
            }

            $key = 'media_quality_step';
            $val = self::_conf($node . $key);
            if (filter_var($val, FILTER_VALIDATE_INT) && $val >= 5) {
                $result[$key] = $val;
            }

            self::_cache($cache_key, json_encode($result));
            return $result;
        }
        return json_decode($result, true);
    }

//	public static function _getImageSize($key)
//	{
//		$default = [160, 120];
//		$val = self::_conf($key);
//		if (!$val) return $default;
//		$val = preg_replace('/[x|,]/i', ':', $val);
//		$val = array_map('trim', explode(':', $val));
//		$val['0'] = $val['0'] ?: null;
//		$val['1'] = $val['1'] ?: null;
//		return $val;
//	}

    public static function _getWebp($url)
    {
        return substr($url, 0, strrpos($url, '.')) . '.webp';
    }


    public static function _getAvif($url)
    {
        return substr($url, 0, strrpos($url, '.')) . '.avif';
    }


    public static function _getSuffix($str)
    {
        return strtolower(substr(strrchr($str, '.'), 1));
    }


    public static function _isImage($ext)
    {
        return in_array(strtolower($ext), ['jpg', 'png', 'webp', 'bmp', 'gif', 'jpeg', 'tiff', 'jfif', 'avif']);
    }


    private static function _getImageCdn()
    {
        $cache_key = 'ImageCdnUrl';
        if (!($val = self::_cache($cache_key))) {
            $cdn = self::_conf('general/image/remote_url');
            $val = [];
            if ($cdn) {
                foreach ($cdn as $c) {
                    if (!$c) continue;
                    $c = array_map('trim', explode('|', $c));
                    $url = $c['0'];
                    if (!$url) continue;
                    if ('/' !== substr($url, -1)) {
                        $url .= '/';
                    }
                    $val[] = ['url' => $url, 'secret' => $url['1'] ?? ''];
                }
            }
            if ($val) {
                self::_cache($cache_key, json_encode($val));
            }
            return $val;
        }
        return json_decode($val, true);
    }


    public static function _getSrc($url, $index = -1)
    {
        $ext = self::_getSuffix($url);
        $node = 'general/image/';
        if (strstr($url, 'media/') && self::_isImage($ext) && self::_conf($node . 'remote_enable')) {
            if (self::_conf($node . 'avif') && SUPPORT_AVIF) {
                $url = self::_getAvif($url);
            } elseif (self::_conf($node . 'webp') && SUPPORT_WEBP) {
                $url = self::_getWebp($url);
            }

            $cdn = self::_getImageCdn();
            if ($cdn) {
                $total = count($cdn);
                $index = ($index === -1 || !filter_var($index, FILTER_VALIDATE_INT)) ? mt_rand(0, $total) : $index % $total;
                $cdn_url = $cdn[abs($index)] ?? $cdn[mt_rand(0, $total)];
                $cdn_url = $cdn_url['url'];

                if (strstr($url, 'media/catalog/')) {
                    if (strstr($url, 'media/catalog/product/cache/')) {
                        $url = str_replace(self::_siteUrl() . 'media/catalog/product/cache/', $cdn_url . 'cache/', $url);
                    } elseif (strstr($url, 'media/catalog/cache/')) {
                        $url = str_replace(self::_siteUrl() . 'media/catalog/cache/', $cdn_url . 'cache/', $url);
                    } else {
                        $url = str_replace(self::_siteUrl() . 'media/catalog/', $cdn_url . 'cache/', $url);
                    }
                } else {
                    $url = str_replace(self::_siteUrl() . 'media/', $cdn_url, $url);
                }
            }
        }
        return $url;
    }


    public static function _getSkuSrc($sku)
    {
        return self::_getSrc(self::_siteUrl() . 'media/sku/' . md5(trim($sku)) . '.jpg');
    }

//	public static function _getSrcHtml($url, $cdn_index = 0)
//	{
//		$html = '<picture>';
//		$url = self::_getSrc($url, $cdn_index);
//		$avif = self::_getAvif($url);
//		$webp = self::_getWebp($url);
//		$html .= '<source srcset="' . $avif . '" type="image/avif">';
//		$html .= '<source srcset="' . $webp . '" type="image/webp">';
//		$html .= '<img src="' . $url . '" alt="">';
//		$html .= '</picture>';
//		return $html;
//	}


    public static function _getStaticUrl($url)
    {
        //https://cdn.jsdelivr.net/gh/luinfy/magelts@master/
        //https://raw.githubusercontent.com/luinfy/magelts/main/
        //https://gitee.com/luinfy/aoin/raw/master/mage/

        if ('/' === substr($url, 0, 1)) {
            $url = substr($url, 1);
        }
        $base = self::_siteUrl();
        $node = 'design/load/';

        if (self::_conf($node . 'remote_mage_enable')) {
            $base = self::_conf($node . 'remote_mage_en') ?: 'https://cdn.jsdelivr.net/gh/luinfy/magelts@master/';
            if (self::_client()->isChina() || self::_client()->isPrivate()) {
                $base = self::_conf($node . 'remote_mage_cn') ?: self::_siteUrl();
            }
            if ($base && '/' !== substr($base, -1)) {
                $base .= '/';
            }
        }
        return $base . $url;
    }

    ###############################################################################################################
    private static function _getSocialLogo($key)
    {
        $logo = self::_conf('general/social/' . $key . '_logo');
        return self::_siteUrl() . 'media/icons/social/' . ($logo ?: $key . '.png');
    }


    public static function _getSocials($key = null)
    {
        if (!self::$_socials) {
            $cache_key = 'SocialAccount';
            if (!$val = self::_getCache($cache_key)) {
                $node = 'general/social/';
                self::$_socials['reviews']['url'] = '//reviews' . DOMAIN;
                self::$_socials['reviews']['logo'] = self::_getSocialLogo('reviews');
                self::$_socials['reviews']['text'] = self::_conf($node . 'reviews_text') ?: 'Reviews';

                $email = self::_conf('trans_email/ident_general/email');
                self::$_socials['email']['url'] = 'mailto:' . $email;
                self::$_socials['email']['logo'] = self::_getSocialLogo('email');
                self::$_socials['email']['text'] = self::_conf('trans_email/ident_general/text') ?: $email;

                $whatsapp = self::_conf($node . 'whatsapp') ?: self::_conf('general/store_information/phone');
                self::$_socials['whatsapp']['url'] = $whatsapp ? '//api.whatsapp.com/send?phone=' . preg_replace('/[^0-9]/', '', $whatsapp) : '';
                self::$_socials['whatsapp']['logo'] = self::_getSocialLogo('whatsapp');
                self::$_socials['whatsapp']['text'] = self::_conf($node . 'whatsapp_text') ?: $whatsapp;

                $instagram = self::_conf($node . 'instagram');
                self::$_socials['instagram']['url'] = $instagram ? '//www.instagram.com/' . $instagram : '';
                self::$_socials['instagram']['logo'] = self::_getSocialLogo('instagram');
                self::$_socials['instagram']['text'] = self::_conf($node . 'instagram_text') ?: $instagram;

                $tiktok = self::_conf($node . 'tiktok');
                self::$_socials['tiktok']['url'] = $tiktok ? '//www.tiktok.com/@' . $tiktok : '';
                self::$_socials['tiktok']['logo'] = self::_getSocialLogo('tiktok');
                self::$_socials['tiktok']['text'] = self::_conf($node . 'tiktok_text') ?: $tiktok;

                $facebook = self::_conf($node . 'facebook');
                self::$_socials['facebook']['url'] = $facebook ? '//www.facebook.com/' . $facebook : '';
                self::$_socials['facebook']['logo'] = self::_getSocialLogo('facebook');
                self::$_socials['facebook']['text'] = self::_conf($node . 'facebook_text') ?: $facebook;

                $twitter = self::_conf($node . 'twitter');
                self::$_socials['twitter']['url'] = $twitter ? '//www.twitter.com/' . $twitter : '';
                self::$_socials['twitter']['logo'] = self::_getSocialLogo('twitter');
                self::$_socials['twitter']['text'] = self::_conf($node . 'twitter_text') ?: $twitter;

                $youtube = self::_conf($node . 'youtube');
                self::$_socials['youtube']['url'] = $youtube ?: '';
                self::$_socials['youtube']['logo'] = self::_getSocialLogo('youtube');
                self::$_socials['youtube']['text'] = self::_conf($node . 'youtube_text') ?: 'Youtube';

                $linktree = self::_conf($node . 'linktree');
                self::$_socials['linktree']['url'] = $linktree ?: '';
                self::$_socials['linktree']['logo'] = self::_getSocialLogo('linktree');
                self::$_socials['linktree']['text'] = self::_conf($node . 'linktree_text') ?: 'LinkTree';

                self::$_socials['scrolltop']['url'] = '';
                self::$_socials['scrolltop']['logo'] = self::_getSocialLogo('scrolltop');
                self::$_socials['scrolltop']['text'] = self::_conf($node . 'scrolltop_text') ?: 'Scroll To Top';

                self::_setCache($cache_key, json_encode(self::$_socials));
            } else {
                self::$_socials = json_decode($val, true);
            }
        }
        return self::$_socials[$key] ?: self::$_socials;
    }

    ###############################################################################################################
    public static function _getBannedComment($mode = 'attack', $msg = null, $operator = null)
    {
        $hash = md5(uniqid(true));
        if (!$msg && $msg != '0') $msg = '';
        $_mode = strtolower($mode);
        switch ($_mode) {
            case 'attack':
                $mode = 'ATT';
                break;
            case 'rate':
            case 'rate_limit':
                $mode = 'RAT';
                break;
            case 'grab':
                $mode = 'GRA';
                break;
            case 'user':
            case 'customer':
                $mode = 'USE';
                break;
            case 'asn':
                $mode = 'ASN';
                break;
            case 'state':
            case 'states':
            case 'province':
            case 'provinces':
                $mode = 'PRO';
                break;
            case 'country':
                $mode = 'COU';
                break;
            case 'city':
                $mode = 'CIT';
                break;
            default:
                $mode = 'UNK';
        }

        $operator = $operator ? 'ADMIN' : (self::_isAdminPath() ? 'SYS' : 'AUTO');
        return $operator . ' | ' . $mode . ' | ' . $hash . ' | ' . $msg . ' | ' . date('d/m/Y H:i:s');
    }


    private static function _isAdminBlock()
    {
        if (self::_client()->isPrivate()) return false;

        $is_block = true;
        $rules = self::_getConfigArray('admin/ufw/admin_rule', null, '//');
        foreach ($rules as $rule) {
            $label = strtolower(substr($rule, 0, 3));

            if (in_array($label, ['asn', 'iso'])) {
                $rule = array_map('trim', explode(',', substr($rule, 4)));
                if (in_array(self::_client()->getISO(), $rule) || in_array(self::_client()->getASN(), $rule)) {
                    $is_block = false;
                    break;
                }
            } elseif (self::_client()->isChina()) {
                $rule = explode(':', $rule);
                $province = trim(strtolower($rule['0']));
                $cities = trim(strtolower($rule['1'] ?? '*'));
                if ($province === strtolower(self::_client()->getProvince()) && ('*' === $cities ||
                        strstr($cities, strtolower(self::_client()->getCity())))) {
                    $is_block = false;
                    break;
                }
            }
        }
        return $is_block;
    }


    public static function _checkAndBlock()
    {
        $node = 'admin/ufw/';
        $sign = self::_conf($node . 'sign') ?: '';
        $debug = self::_conf($node . 'debug');
        $html = self::_conf($node . 'html') ?: '<p>Access Denied!!</p>';
        $develop = self::_conf($node . 'develop');

        $client = self::_client();
        $ip = $client->getIp();

        if ($result = self::_isBanned($ip)) {
            if ($debug) {
                $html .= '<p>' . $result . '</p>';
                $html .= '<p>' . microtime(true) . ' ' . $ip . '</p>';
            }
            echo $html . $sign;
            exit();
        }

        $white_rate = self::_getConfigArray($node . 'rate_white');
        if (self::_conf($node . 'rate_limit') && !in_array($ip, $white_rate)) {
            $key_start = _ufwKeyStart($ip);
            $key_count = _ufwKeyCount($ip);
            $key_ban = _ufwKeyBan($ip);

            $watch_time = self::_conf($node . 'rate_watch_time') ?: 259200;
            $min_threshold = self::_conf($node . 'rate_min_threshold') ?: 20;
            $min_period = self::_conf($node . 'rate_min_period') ?: 100;
            $min_ban_time = self::_conf($node . 'rate_min_ban_time') ?: 21600;
            $max_threshold = self::_conf($node . 'rate_max_threshold') ?: 360;
            $max_period = self::_conf($node . 'rate_max_period') ?: 3600;
            $max_ban_time = self::_conf($node . 'rate_max_ban_time') ?: 2592000;

            $current_period = 1;
            if (!($result = self::_getRedis($key_start))) {
                self::_setRedis($key_start, time(), $watch_time);
            } else {
                $watch_time = self::_redis()->ttl($key_start);
                $current_period = time() - $result;
            }
            $count = self::_getRedis($key_count) ?? 0;
            self::_setRedis($key_count, $count + 1, $watch_time);
            $current_rate = $count / $current_period;
            if ($current_period > $min_period) {
                $rule_rate = $max_threshold / $max_period;
                $ban_time = $max_ban_time;
            } else {
                $rule_rate = $min_threshold / $min_period;
                $ban_time = $min_ban_time;
            }

            if ($current_rate >= $rule_rate) {
                $comment = self::_getBannedComment('rate', $count . ',' . $current_period);
                self::_setRedis($key_ban, $comment, $ban_time);
                self::_delRedis($key_start);
                self::_delRedis($key_count);
                if ($debug) {
                    $html .= '<p>' . $comment . '</p><p>' . microtime(true) . ' ' . $ip . '</p>';
                }
                echo $html . $sign;
                exit();
            }
        }

        $is_block = false;
        //ASN,Country 移至到Cloudflare防火墙规则
//		$asn = self::_conf($node . 'enable_asn') ? self::_getConfigArray($node . 'asn', null, '//') : [];
//		$country = self::_conf($node . 'enable_country') ? self::_getConfigArray($node . 'country', ',') : [];
//		if (in_array(self::_client()->getISO(), $country) || in_array(self::_client()->getASN(), $asn)) {
//			$is_block = true;
//		}
        $agent = self::_conf($node . 'enable_agent') ? self::_getConfigArray($node . 'agent', '|') : [];
        $comp = self::_conf($node . 'enable_comp') ? self::_getConfigArray($node . 'comp', ',') : [];
        foreach ($agent as $v) {
            if (strstr(strtolower($client->getAgent()), strtolower($v))) {
                $is_block = true;
                break;
            }
        }
        foreach ($comp as $v) {
            if (strstr(strtolower($client->getComp()), strtolower($v))) {
                $is_block = true;
                break;
            }
        }

        if (self::_isAdminPath() || $develop || $client->isChina()) {
            $is_block = self::_isAdminBlock();
        } else {
            if (self::_conf($node . 'chinese_browser') && CHINESE_BROWSER) {
                $is_block = true;
            }
            $rules = self::_getConfigArray($node . 'rule', null, '//');
            foreach ($rules as $rule) {
                $rule = explode(':', $rule);
                $cities = $rule['1'] ? strtolower($rule['1']) : '*';
                if ($rule['0'] === $client->getISO() && ('*' === $cities ||
                        strstr($cities, strtolower(str_replace(' ', '', $client->getCity()))))) {
                    $is_block = true;
                    break;
                }
            }
        }

        if ($is_block) {
            if ($debug) {
                $html .= '<p>' . microtime(true) . ', ' . $ip . ', ' . ($client->getCity() ?? 'City') . ', ';
                $html .= ($client->getProvince() ?? 'States') . ', ' . ($client->getCountry() ?? 'Country') . '</p>';
            }
            echo $html . $sign;
            exit();
        }
    }

    /*
    ###############################################################################################################
    ###############################################################################################################
    ###############################################################################################################
    ###############################################################################################################
    ###############################################################################################################
    ###############################################################################################################
    */

    private static function _redisConn()
    {
        if (null === self::$_redis) {
            $xml = simplexml_load_file(_getConfigFile());
            $node = (string)$xml->global->cache->backend_options;

            if ($node) {
                $server = (string)$xml->global->cache->backend_options->server;
                $port = (string)$xml->global->cache->backend_options->port;
                $data = (string)$xml->global->cache->backend_options->database;
                $pwd = (string)$xml->global->cache->backend_options->password;

                self::$_redis = new Redis();
                self::$_redis->pconnect($server, $port);
                self::$_redis->select($data);
                if ($pwd) {
                    self::$_redis->auth($pwd);
                }
            }
        }
        return self::$_redis;
    }


    public static function _redis($key, $value = null, $timeout = null)
    {
        $redis = self::_redisConn();
        if ($redis) {
            if ($value !== null) {
                $timeout = (filter_var($timeout, FILTER_VALIDATE_INT) && $timeout > 0) ? $timeout : 31536000;
                $value = is_array($value) ? json_encode($value) : $value;
                return $redis->set($key, $value) ? $redis->expire($key, $timeout) : false;
            } else {
                return $redis->get($key);
            }
        }
        return false;
    }


    public static function _redisDel($key)
    {
        return ($redis = self::_redisConn()) ? $redis->del($key) : false;
    }


    public static function _cache($key, $value = null, $timeout = null)
    {
        if (null !== $value) {
            $timeout = (filter_var($timeout, FILTER_VALIDATE_INT) && $timeout > 0) ? $timeout : 31536000;
            $value = is_array($value) ? json_encode($value) : $value;
            return self::app()->getCache()->save($value, $key, AOIN_CACHE_KEY, $timeout);
        } else {
            return self::app()->getCache()->load($key);
        }
    }


    public static function _isBanned($ip = null)
    {
        return self::_redis(_ufwKeyBan($ip));
    }


    public static function _addBanned($ip, $comment, $timeout = null)
    {
        return self::_redis(_ufwKeyBan($ip), $comment, $timeout);
    }


    public static function _delBanned($ip)
    {
        return self::_redisDel(_ufwKeyBan($ip));
    }

    ###############################################################################################################
    private static function _pdoConn()
    {
        //Mage::getSingleton('core/resource')->getConnection('core_write')
        //return Mage::getSingleton('core/resource')->getConnection('core_read');
        $xml = simplexml_load_file(_getConfigFile());
        $dbname = (string)$xml->global->resources->default_setup->connection->dbname;
        $host = (string)$xml->global->resources->default_setup->connection->host;
        $user = (string)$xml->global->resources->default_setup->connection->username;
        $pwd = (string)$xml->global->resources->default_setup->connection->password;
        $port = 3306;
        if (strstr($host, ':')) {
            $_host = explode(':', $host);
            $host = $_host['0'];
            $port = $_host['1'];
        }

        $dsn = 'mysql:host=' . $host . ';port=' . $port . ';dbname=' . $dbname . ';charset=utf8';
        $option = [
            PDO::ATTR_PERSISTENT => true,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            //PDO::MYSQL_ATTR_LOCAL_INFILE => true
        ];
        return new PDO($dsn, $user, $pwd, $option);
    }


    private static function _pdoQuery($sql, $values = null)
    {
        if (self::isInstalled() && is_string($sql)) {
            $conn = self::_pdoConn();
            if (null !== $conn) {
                $db = $conn->prepare($sql);
                if (is_array($values)) {
                    $db->execute($values);
                } else {
                    $db->execute();
                }
                $method = strtolower(substr($sql, 0, 6));
                if ($db->rowCount() >= 0) {
                    if ('select' === $method) {
                        $result = [];
                        while ($row = $db->fetch()) {
                            $result[] = $row;
                        }
                        return $result;
                    } else {
                        return $db->rowCount();
                    }
                }
            }
        }
        return null;
    }




    /*

    ###############################################################################################################






    public static function _getCurrentStoreVid()
    {
        if (-1 === self::$_store_id) {
            $sql = 'SELECT scope_id';
            $sql .= ' FROM core_config_data';
            $sql .= ' WHERE path=\'web/secure/base_url\' AND value like \'%' . $_SERVER['HTTP_HOST'] . '%\'';
            $domains = self::_pdoQuery($sql);
            if ($domains) {
                self::$_store_id = $domains['0']['scope_id'];
            } else {
                self::$_store_id = null;
            }
        }
        return self::$_store_id;
    }


    public static function _getCurrentStoreCode()
    {
        if (!self::$_store_code) {
            if ($vid = self::_getCurrentStoreVid()) {
                $stores = self::_pdoQuery('SELECT code FROM core_store WHERE store_id=' . $vid);
                if ($stores) {
                    self::$_store_code = $stores['0']['code'];
                }
            }
        }
        return self::$_store_code ?: 'default';
    }


    private static function _checkCouponActive($id)
    {
        $sql = 'SELECT COUNT(rule_id) FROM salesrule WHERE rule_id=:id AND to_date>:to_date AND is_active = 1';
        return self::_pdoQuery($sql, [':id' => $id, ':to_date' => date('Y-m-d')]);
    }


    public static function _isAutoDiscount()
    {
        $sql = 'SELECT rule_id FROM salesrule_coupon WHERE code=\'\'';
        $result = self::_pdoQuery($sql);
        if ($result) {
            foreach ($result as $v) {
                if (self::_checkCouponActive($v['rule_id'])) {
                    return true;
                }
            }
        }
        return false;
    }


    public static function _getCustomerIps($cid)
    {
        $sql = 'SELECT x_forwarded_for AS ip FROM sales_flat_order WHERE customer_id=:cid';
        return self::_pdoQuery($sql, [':cid' => $cid]);
    }
    */
    ###############################################################################################################
    //luinfy:end
    ###############################################################################################################


    /**
     * Registry collection
     *
     * @var array
     */
    static private $_registry = [];

    /**
     * Application root absolute path
     *
     * @var string
     */
    static private $_appRoot;

    /**
     * Application model
     *
     * @var Mage_Core_Model_App
     */
    static private $_app;

    /**
     * Config Model
     *
     * @var Mage_Core_Model_Config
     */
    static private $_config;

    /**
     * Event Collection Object
     *
     * @var Varien_Event_Collection
     */
    static private $_events;

    /**
     * Object cache instance
     *
     * @var Varien_Object_Cache
     */
    static private $_objects;

    /**
     * Is developer mode flag
     *
     * @var bool
     */
    static private $_isDeveloperMode = false;

    /**
     * Is allow throw Exception about headers already sent
     *
     * @var bool
     */
    public static $headersSentThrowsException = true;

    /**
     * Is installed flag
     *
     * @var bool
     */
    static private $_isInstalled;

    /**
     * Magento edition constants
     */
    const EDITION_COMMUNITY = 'Community';
    const EDITION_ENTERPRISE = 'Enterprise';
    //const EDITION_PROFESSIONAL = 'Professional';
    //const EDITION_GO           = 'Go';

    /**
     * Current Magento edition.
     *
     * @var string
     * @static
     */
    static private $_currentEdition = self::EDITION_COMMUNITY;

    /**
     * Gets the current Magento version string
     *
     * @return string
     */
    public static function getVersion()
    {
        $i = self::getVersionInfo();
        return trim("{$i['major']}.{$i['minor']}.{$i['revision']}" . ($i['patch'] != '' ? ".{$i['patch']}" : "")
            . "-{$i['stability']}{$i['number']}", '.-');
    }

    /**
     * Gets the detailed Magento version information
     *
     * @return array
     */
    public static function getVersionInfo()
    {
        return [
            'major' => '1',
            'minor' => '9',
            'revision' => '4',
            'patch' => '5',
            'stability' => '',
            'number' => '',
        ];
    }

    /**
     * Gets the current OpenMage version string
     * @link https://openmage.github.io/supported-versions.html
     * @link https://semver.org/
     *
     * @return string
     */
    public static function getOpenMageVersion()
    {
        $i = self::getOpenMageVersionInfo();
        $versionString = "{$i['major']}.{$i['minor']}.{$i['patch']}";
        if ($i['stability'] || $i['number']) {
            $versionString .= "-";
            if ($i['stability'] && $i['number']) {
                $versionString .= implode('.', [$i['stability'], $i['number']]);
            } else {
                $versionString .= implode('', [$i['stability'], $i['number']]);
            }
        }
        return trim(
            $versionString,
            '.-'
        );
    }

    /**
     * Gets the detailed OpenMage version information
     * @link https://openmage.github.io/supported-versions.html
     * @link https://semver.org/
     *
     * @return array
     */
    public static function getOpenMageVersionInfo()
    {
        return [
            'major' => '20',
            'minor' => '0',
            'patch' => '16',
            'stability' => '', // beta,alpha,rc
            'number' => '', // 1,2,3,0.3.7,x.7.z.92 @see https://semver.org/#spec-item-9
        ];
    }

    /**
     * Get current Magento edition
     *
     * @static
     * @return string
     */
    public static function getEdition()
    {
        return self::$_currentEdition;
    }

    /**
     * Set all my static data to defaults
     *
     */
    public static function reset()
    {
        self::$_registry = [];
        self::$_appRoot = null;
        self::$_app = null;
        self::$_config = null;
        self::$_events = null;
        self::$_objects = null;
        self::$_isDeveloperMode = false;
        self::$_isInstalled = null;
        // do not reset $headersSentThrowsException
    }

    /**
     * Register a new variable
     *
     * @param string $key
     * @param mixed $value
     * @param bool $graceful
     * @throws Mage_Core_Exception
     */
    public static function register($key, $value, $graceful = false)
    {
        if (isset(self::$_registry[$key])) {
            if ($graceful) {
                return;
            }
            self::throwException('Mage registry key "' . $key . '" already exists');
        }
        self::$_registry[$key] = $value;
    }

    /**
     * Unregister a variable from register by key
     *
     * @param string $key
     */
    public static function unregister($key)
    {
        if (isset(self::$_registry[$key])) {
            if (is_object(self::$_registry[$key]) && (method_exists(self::$_registry[$key], '__destruct'))) {
                self::$_registry[$key]->__destruct();
            }
            unset(self::$_registry[$key]);
        }
    }

    /**
     * Retrieve a value from registry by a key
     *
     * @param string $key
     * @return mixed
     */
    public static function registry($key)
    {
        return self::$_registry[$key] ?? null;
    }

    /**
     * Set application root absolute path
     *
     * @param string $appRoot
     * @throws Mage_Core_Exception
     */
    public static function setRoot($appRoot = '')
    {
        if (self::$_appRoot) {
            return;
        }

        if ($appRoot === '') {
            // automagically find application root by dirname of Mage.php
            $appRoot = dirname(__FILE__);
        }

        $appRoot = realpath($appRoot);

        if (is_dir($appRoot) && is_readable($appRoot)) {
            self::$_appRoot = $appRoot;
        } else {
            self::throwException($appRoot . ' is not a directory or not readable by this user');
        }
    }

    /**
     * Retrieve application root absolute path
     *
     * @return string
     */
    public static function getRoot()
    {
        return self::$_appRoot;
    }

    /**
     * Retrieve Events Collection
     *
     * @return Varien_Event_Collection $collection
     */
    public static function getEvents()
    {
        return self::$_events;
    }

    /**
     * Varien Objects Cache
     *
     * @param string $key optional, if specified will load this key
     * @return Varien_Object_Cache|object
     */
    public static function objects($key = null)
    {
        if (!self::$_objects) {
            self::$_objects = new Varien_Object_Cache;
        }
        if (is_null($key)) {
            return self::$_objects;
        } else {
            return self::$_objects->load($key);
        }
    }

    /**
     * Retrieve application root absolute path
     *
     * @param string $type
     * @return string
     */
    public static function getBaseDir($type = 'base')
    {
        //return self::getConfig()->getOptions()->getDir($type);
        //luinfy::start
        if (in_array($type, ['js', 'skin', 'media', 'errors']) && defined('AOIN_PUB_ROOT')) {
            return AOIN_PUB_ROOT . '/' . $type;
        } else {
            return self::getConfig()->getOptions()->getDir($type);
        }
        //luinfy::end
    }

    /**
     * Retrieve module absolute path by directory type
     *
     * @param string $type
     * @param string $moduleName
     * @return string
     */
    public static function getModuleDir($type, $moduleName)
    {
        return self::getConfig()->getModuleDir($type, $moduleName);
    }

    /**
     * Retrieve config value for store by path
     *
     * @param string $path
     * @param null|string|bool|int|Mage_Core_Model_Store $store
     * @return array|string|null
     */
    public static function getStoreConfig($path, $store = null)
    {
        return self::app()->getStore($store)->getConfig($path);
    }

    /**
     * Retrieve config flag for store by path
     *
     * @param string $path
     * @param mixed $store
     * @return bool
     */
    public static function getStoreConfigFlag($path, $store = null)
    {
        $flag = strtolower(self::getStoreConfig($path, $store));
        if (!empty($flag) && $flag !== 'false') {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Get base URL path by type
     *
     * @param string $type
     * @param null|bool $secure
     * @return string
     */
    public static function getBaseUrl($type = Mage_Core_Model_Store::URL_TYPE_LINK, $secure = null)
    {
        return self::app()->getStore()->getBaseUrl($type, $secure);
    }

    /**
     * Generate url by route and parameters
     *
     * @param string $route
     * @param array $params
     * @return  string
     */
    public static function getUrl($route = '', $params = [])
    {
        return self::getModel('core/url')->getUrl($route, $params);
    }

    /**
     * Get design package singleton
     *
     * //return Mage_Core_Model_Design_Package
     * return Mage_Core_Model_Abstract
     */
    public static function getDesign()
    {
        return self::getSingleton('core/design_package');
    }

    /**
     * Retrieve a config instance
     *
     * @return Mage_Core_Model_Config
     */
    public static function getConfig()
    {
        return self::$_config;
    }

    /**
     * Add observer to even object
     *
     * @param string $eventName
     * @param callback $callback
     * @param array $data
     * @param string $observerName
     * @param string $observerClass
     * @return Varien_Event_Collection
     */
    public static function addObserver($eventName, $callback, $data = [], $observerName = '', $observerClass = '')
    {
        if ($observerClass == '') {
            $observerClass = 'Varien_Event_Observer';
        }
        $observer = new $observerClass();
        $observer->setName($observerName)->addData($data)->setEventName($eventName)->setCallback($callback);
        return self::getEvents()->addObserver($observer);
    }

    /**
     * Dispatch event
     *
     * Calls all observer callbacks registered for this event
     * and multiple observers matching event name pattern
     *
     * @param string $name
     * @param array $data
     * @return Mage_Core_Model_App
     */
    public static function dispatchEvent($name, array $data = [])
    {
        Varien_Profiler::start('DISPATCH EVENT:' . $name);
        $result = self::app()->dispatchEvent($name, $data);
        Varien_Profiler::stop('DISPATCH EVENT:' . $name);
        return $result;
    }

    /**
     * Retrieve model object
     *
     * @param string $modelClass
     * @param array|object $arguments
     * @return  Mage_Core_Model_Abstract|false
     * @link    Mage_Core_Model_Config::getModelInstance
     */
    public static function getModel($modelClass = '', $arguments = [])
    {
        return self::getConfig()->getModelInstance($modelClass, $arguments);
    }

    /**
     * Retrieve model object singleton
     *
     * @param string $modelClass
     * @param array $arguments
     * @return  Mage_Core_Model_Abstract
     */
    public static function getSingleton($modelClass = '', array $arguments = [])
    {
        $registryKey = '_singleton/' . $modelClass;
        if (!isset(self::$_registry[$registryKey])) {
            self::register($registryKey, self::getModel($modelClass, $arguments));
        }
        return self::$_registry[$registryKey];
    }

    /**
     * Retrieve object of resource model
     *
     * @param string $modelClass
     * @param array $arguments
     * @return  Mage_Core_Model_Resource_Db_Collection_Abstract|false
     */
    public static function getResourceModel($modelClass, $arguments = [])
    {
        return self::getConfig()->getResourceModelInstance($modelClass, $arguments);
    }

    /**
     * Retrieve Controller instance by ClassName
     *
     * @param string $class
     * @param Mage_Core_Controller_Request_Http $request
     * @param Mage_Core_Controller_Response_Http $response
     * @param array $invokeArgs
     * @return Mage_Core_Controller_Front_Action
     */
    public static function getControllerInstance($class, $request, $response, array $invokeArgs = [])
    {
        return new $class($request, $response, $invokeArgs);
    }

    /**
     * Retrieve resource vodel object singleton
     *
     * @param string $modelClass
     * @param array $arguments
     * @return  object
     */
    public static function getResourceSingleton($modelClass = '', array $arguments = [])
    {
        $registryKey = '_resource_singleton/' . $modelClass;
        if (!isset(self::$_registry[$registryKey])) {
            self::register($registryKey, self::getResourceModel($modelClass, $arguments));
        }
        return self::$_registry[$registryKey];
    }

    /**
     * @param string $type
     * @return object|bool
     * @deprecated, use self::helper()
     *
     */
    public static function getBlockSingleton($type)
    {
        $action = self::app()->getFrontController()->getAction();
        return $action ? $action->getLayout()->getBlockSingleton($type) : false;
    }

    /**
     * Retrieve helper object
     *
     * @param string $name the helper name
     * @return Mage_Core_Helper_Abstract
     */
    public static function helper($name)
    {
        $registryKey = '_helper/' . $name;
        if (!isset(self::$_registry[$registryKey])) {
            $helperClass = self::getConfig()->getHelperClassName($name);
            self::register($registryKey, new $helperClass);
        }
        return self::$_registry[$registryKey];
    }

    /**
     * Retrieve resource helper object
     *
     * @param string $moduleName
     * @return Mage_Core_Model_Resource_Helper_Abstract
     */
    public static function getResourceHelper($moduleName)
    {
        $registryKey = '_resource_helper/' . $moduleName;
        if (!isset(self::$_registry[$registryKey])) {
            $helperClass = self::getConfig()->getResourceHelper($moduleName);
            self::register($registryKey, $helperClass);
        }
        return self::$_registry[$registryKey];
    }

    /**
     * Return new exception by module to be thrown
     *
     * @param string $module
     * @param string $message
     * @param integer $code
     * @return Mage_Core_Exception
     */
    public static function exception($module = 'Mage_Core', $message = '', $code = 0)
    {
        $className = $module . '_Exception';
        return new $className($message, $code);
    }

    /**
     * Throw Exception
     *
     * @param string $message
     * @param string $messageStorage
     * @throws Mage_Core_Exception
     */
    public static function throwException($message, $messageStorage = null)
    {
        if ($messageStorage && ($storage = self::getSingleton($messageStorage))) {
            $storage->addError($message);
        }
        throw new Mage_Core_Exception($message);
    }

    /**
     * Get initialized application object.
     *
     * @param string $code
     * @param string $type
     * @param string|array $options
     * @return Mage_Core_Model_App
     */
    public static function app($code = '', $type = 'store', $options = [])
    {
        if (self::$_app === null) {
            self::$_app = new Mage_Core_Model_App();
            self::setRoot();
            self::$_events = new Varien_Event_Collection();
            self::_setIsInstalled($options);
            self::_setConfigModel($options);

            Varien_Profiler::start('self::app::init');
            self::$_app->init($code, $type, $options);
            Varien_Profiler::stop('self::app::init');
            self::$_app->loadAreaPart(Mage_Core_Model_App_Area::AREA_GLOBAL, Mage_Core_Model_App_Area::PART_EVENTS);
        }
        return self::$_app;
    }

    /**
     * @static
     * @param string $code
     * @param string $type
     * @param array $options
     * @param string|array $modules
     */
    public static function init($code = '', $type = 'store', $options = [], $modules = [])
    {
        try {
            self::setRoot();
            self::$_app = new Mage_Core_Model_App();
            self::_setIsInstalled($options);
            self::_setConfigModel($options);

            if (!empty($modules)) {
                self::$_app->initSpecified($code, $type, $options, $modules);
            } else {
                self::$_app->init($code, $type, $options);
            }
        } catch (Mage_Core_Model_Session_Exception $e) {
            header('Location: ' . self::getBaseUrl());
            die;
        } catch (Mage_Core_Model_Store_Exception $e) {
            require_once(self::getBaseDir() . DS . 'errors' . DS . '404.php');
            die;
        } catch (Exception $e) {
            self::printException($e);
            die;
        }
    }

    /**
     * Front end main entry point
     *
     * @param string $code
     * @param string $type
     * @param string|array $options
     */
    public static function run($code = '', $type = 'store', $options = [])
    {
        try {
            Varien_Profiler::start('mage');
            self::setRoot();
            if (isset($options['edition'])) {
                self::$_currentEdition = $options['edition'];
            }
            self::$_app = new Mage_Core_Model_App();
            if (isset($options['request'])) {
                self::$_app->setRequest($options['request']);
            }
            if (isset($options['response'])) {
                self::$_app->setResponse($options['response']);
            }
            self::$_events = new Varien_Event_Collection();
            self::_setIsInstalled($options);
            self::_setConfigModel($options);
            self::$_app->run([
                'scope_code' => $code,
                'scope_type' => $type,
                'options' => $options,
            ]);
            Varien_Profiler::stop('mage');
        } catch (Mage_Core_Model_Session_Exception $e) {
            header('Location: ' . self::getBaseUrl());
            die();
        } catch (Mage_Core_Model_Store_Exception $e) {
            require_once(self::getBaseDir() . DS . 'errors' . DS . '404.php');
            die();
        } catch (Exception $e) {
            if (self::isInstalled()) {
                self::printException($e);
                exit();
            }
            try {
                self::dispatchEvent('mage_run_exception', ['exception' => $e]);
                if (!headers_sent() && self::isInstalled()) {
                    header('Location:' . self::getUrl('install'));
                } else {
                    self::printException($e);
                }
            } catch (Exception $ne) {
                self::printException($ne, $e->getMessage());
            }
        }
    }

    /**
     * Set application isInstalled flag based on given options
     *
     * @param array $options
     */
    protected static function _setIsInstalled($options = [])
    {
        if (isset($options['is_installed']) && $options['is_installed']) {
            self::$_isInstalled = true;
        }
    }

    /**
     * Set application Config model
     *
     * @param array $options
     */
    protected static function _setConfigModel($options = [])
    {
        if (isset($options['config_model']) && class_exists($options['config_model'])) {
            $alternativeConfigModelName = $options['config_model'];
            unset($options['config_model']);
            $alternativeConfigModel = new $alternativeConfigModelName($options);
        } else {
            $alternativeConfigModel = null;
        }

        if ($alternativeConfigModel instanceof Mage_Core_Model_Config) {
            #if (!is_null($alternativeConfigModel) && ($alternativeConfigModel instanceof Mage_Core_Model_Config)) {
            self::$_config = $alternativeConfigModel;
        } else {
            self::$_config = new Mage_Core_Model_Config($options);
        }
    }

    /**
     * Retrieve application installation flag
     *
     * @param string|array $options
     * @return bool
     */
    public static function isInstalled($options = [])
    {
        if (self::$_isInstalled === null) {
            self::setRoot();

            if (is_string($options)) {
                $options = ['etc_dir' => $options];
            }
            $etcDir = self::getRoot() . DS . 'etc';
            if (!empty($options['etc_dir'])) {
                $etcDir = $options['etc_dir'];
            }

            //$localConfigFile = $etcDir . DS . 'local.xml';
            //luinfy:start
            $localConfigFile = _getConfigFile();
            //luinfy:end

            self::$_isInstalled = false;

            if (is_readable($localConfigFile)) {
                $localConfig = simplexml_load_file($localConfigFile);
                date_default_timezone_set('UTC');
                if (($date = $localConfig->global->install->date) && strtotime($date)) {
                    self::$_isInstalled = true;
                }
            }
        }
        return self::$_isInstalled;
    }

    /**
     * log facility (??)
     *
     * @param array|object|string $message
     * @param int $level
     * @param string $file
     * @param bool $forceLog
     */
    public static function log($message, $level = null, $file = '', $forceLog = false)
    {
        if (!self::getConfig()) {
            return;
        }

        try {
            $logActive = self::getStoreConfig('dev/log/active');
            if (empty($file)) {
                $file = self::getStoreConfig('dev/log/file');
            }
        } catch (Exception $e) {
            $logActive = true;
        }

        if (!self::$_isDeveloperMode && !$logActive && !$forceLog) {
            return;
        }

        static $loggers = [];

        try {
            $maxLogLevel = (int)self::getStoreConfig('dev/log/max_level');
        } catch (Throwable $e) {
            $maxLogLevel = Zend_Log::DEBUG;
        }

        $level = is_null($level) ? Zend_Log::DEBUG : $level;

        if (!self::$_isDeveloperMode && $level > $maxLogLevel) {
            return;
        }

        $file = empty($file) ?
            (string)self::getConfig()->getNode('dev/log/file', Mage_Core_Model_Store::DEFAULT_CODE) : basename($file);

        try {
            if (!isset($loggers[$file])) {
                // Validate file extension before save. Allowed file extensions: log, txt, html, csv
                $_allowedFileExtensions = explode(
                    ',',
                    (string)self::getConfig()->getNode('dev/log/allowedFileExtensions', Mage_Core_Model_Store::DEFAULT_CODE)
                );
                if (!($extension = pathinfo($file, PATHINFO_EXTENSION)) || !in_array($extension, $_allowedFileExtensions)) {
                    return;
                }

                $logDir = self::getBaseDir('var') . DS . 'log';
                $logFile = $logDir . DS . $file;

                if (!is_dir($logDir)) {
                    mkdir($logDir);
                    chmod($logDir, 0750);
                }

                if (!file_exists($logFile)) {
                    file_put_contents($logFile, '');
                    chmod($logFile, 0640);
                }

                $format = '%timestamp% %priorityName% (%priority%): %message%' . PHP_EOL;
                $formatter = new Zend_Log_Formatter_Simple($format);
                $writerModel = (string)self::getConfig()->getNode('global/log/core/writer_model');
                if (!self::$_app || !$writerModel) {
                    $writer = new Zend_Log_Writer_Stream($logFile);
                } else {
                    $writer = new $writerModel($logFile);
                }
                $writer->setFormatter($formatter);
                $loggers[$file] = new Zend_Log($writer);
            }

            if (is_array($message) || is_object($message)) {
                $message = print_r($message, true);
            }

            $message = addcslashes($message, '<?');
            $loggers[$file]->log($message, $level);
        } catch (Exception $e) {
        }
    }

    /**
     * Write exception to log
     *
     * @param Throwable $e
     */
    public static function logException(Throwable $e)
    {
        if (!self::getConfig()) {
            return;
        }
        $file = self::getStoreConfig('dev/log/exception_file');
        self::log("\n" . $e->__toString(), Zend_Log::ERR, $file);
    }

    /**
     * Set enabled developer mode
     *
     * @param bool $mode
     * @return bool
     */
    public static function setIsDeveloperMode($mode)
    {
        self::$_isDeveloperMode = (bool)$mode;
        return self::$_isDeveloperMode;
    }

    /**
     * Retrieve enabled developer mode
     *
     * @return bool
     */
    public static function getIsDeveloperMode()
    {
        return self::$_isDeveloperMode;
    }

    /**
     * Display exception
     *
     * @param Throwable $e
     * @param string $extra
     */
    public static function printException(Throwable $e, $extra = '')
    {
        if (self::$_isDeveloperMode) {
            @http_response_code(500);
            print '<pre>';

            if (!empty($extra)) {
                print $extra . "\n\n";
            }

            print $e->getMessage() . "\n\n";
            print $e->getTraceAsString();
            print '</pre>';
        } else {

            $reportData = [
                (!empty($extra) ? $extra . "\n\n" : '') . $e->getMessage(),
                $e->getTraceAsString()
            ];

            // retrieve server data
            if (isset($_SERVER)) {
                if (isset($_SERVER['REQUEST_URI'])) {
                    $reportData['url'] = $_SERVER['REQUEST_URI'];
                }
                if (isset($_SERVER['SCRIPT_NAME'])) {
                    $reportData['script_name'] = $_SERVER['SCRIPT_NAME'];
                }
            }

            // attempt to specify store as a skin
            try {
                $storeCode = self::app()->getStore()->getCode();
                $reportData['skin'] = $storeCode;
            } catch (Exception $e) {
            }

            require_once(self::getBaseDir() . DS . 'errors' . DS . 'report.php');
        }

        die();
    }

    /**
     * Define system folder directory url by virtue of running script directory name
     * Try to find requested folder by shifting to domain root directory
     *
     * @param string $folder
     * @param boolean $exitIfNot
     * @return  string
     */
    public static function getScriptSystemUrl($folder, $exitIfNot = false)
    {
        $runDirUrl = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
        $runDir = rtrim(dirname($_SERVER['SCRIPT_FILENAME']), DS);

        $baseUrl = null;
        if (is_dir($runDir . '/' . $folder)) {
            $baseUrl = str_replace(DS, '/', $runDirUrl);
        } else {
            $runDirUrlArray = explode('/', $runDirUrl);
            $runDirArray = explode('/', $runDir);
            $count = count($runDirArray);

            for ($i = 0; $i < $count; $i++) {
                array_pop($runDirUrlArray);
                array_pop($runDirArray);
                $_runDir = implode('/', $runDirArray);
                if (!empty($_runDir)) {
                    $_runDir .= '/';
                }
                
                if (is_dir($_runDir . $folder)) {
                    $_runDirUrl = implode('/', $runDirUrlArray);
                    $baseUrl = str_replace(DS, '/', $_runDirUrl);
                    break;
                }
            }
        }

        if (is_null($baseUrl)) {
            $errorMessage = "Unable detect system directory: $folder";
            if ($exitIfNot) {
                // exit because of infinity loop
                exit($errorMessage);
            } else {
                self::printException(new Exception(), $errorMessage);
            }
        }

        return $baseUrl;
    }
}
