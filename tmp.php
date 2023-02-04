<?php
/**
 * Created by PhpStorm.
 * User: Luin
 * Date: 2018/11/12
 * Time: 11:02
 */

namespace Ylu\Base;


class Base
{

    private static $_digit = '0123456789';


    private static $_character = 'abcdefghijklmnopqrstuvwxyz';


    private static $_special = '~!@#$%^&*()_+-[];{}:=|<>?/`\'\\,."';


    private static $_rome = 'αβγδεζηθικλμνξοπρστυφχψω';


    private static $_date = 'Y-m-d';


    private static $_datetime = 'Y-m-d H:i:s';


    // random encodeID base
    private static $_encode_base = 56;


    // random encodeID start
    private static $_encode_start = 31098765432;


    public static function isStr($str)
    {
        return $str && is_string($str);
    }


    public static function isArr($arr)
    {
        return $arr && is_array($arr);
    }


    public static function isObj($obj)
    {
        return $obj && is_object($obj);
    }


    public static function isPlus($num)
    {
        return is_int($num) && $num > 0;
    }


    public static function dateFmt()
    {
        return (defined('FMT_DATE') && self::isStr(FMT_DATE)) ? FMT_DATE : self::$_date;
    }


    public static function datetimeFmt()
    {
        return (defined('FMT_DATETIME') &&
            self::isStr(FMT_DATETIME)) ? FMT_DATETIME : self::$_datetime;
    }


    public static function camel2under($str)
    {
        if (!self::isStr($str)) return false;
        return strtolower(preg_replace('/(?<=[a-z])([A-Z])/', '_$1', $str));
    }


    public static function under2camel($str)
    {
        if (!self::isStr($str)) return false;
        $str = preg_replace_callback('/([-_]+([a-z]{1}))/i', function ($matches) {
            return strtoupper($matches[2]);
        }, $str);
        return $str;
    }


    public static function digit()
    {
        return self::$_digit;
    }


    public static function lower($str = null, $encode = 'ascii')
    {
        if (self::isStr($str)) {
            return strtolower($encode) === 'ascii' ? strtolower($str) : mb_strtolower($str);
        } else {
            return self::$_character;
        }
    }


    public static function upper($str = null, $encode = 'ascii')
    {
        if (self::isStr($str)) {
            return strtolower($encode) === 'ascii' ? strtoupper($str) : mb_strtoupper($str);
        } else {
            return strtoupper(self::$_character);
        }
    }


    public static function special($len = 14)
    {
        if (self::isPlus($len)) {
            return substr(self::$_special, 0, $len);
        } else {
            return self::$_special;
        }
    }


    public static function rome($capital = false)
    {
        if ($capital === true) {
            return mb_strtoupper(self::$_rome);
        } elseif (is_int($capital)) {
            return self::$_rome . mb_strtoupper(self::$_rome);
        } else {
            return self::$_rome;
        }
    }


    public static function character()
    {
        return self::$_character . strtoupper(self::$_character);
    }


    public static function now()
    {
        return date(self::datetimeFmt());
    }


    public static function today()
    {
        return date(self::dateFmt());
    }


    public static function stamp($time = null)
    {
        if (filter_var($time, FILTER_VALIDATE_INT)) {
            return (int)$time;
        } elseif (self::isStr($time)) {
            return strtotime($time);
        } else {
            return time();
        }
    }


    public static function addSecond($second, $start = null, $date_format = false)
    {
        if ($date_format === true) {
            return date(self::dateFmt(), self::stamp($start) + $second);
        } else {
            return date(self::datetimeFmt(), self::stamp($start) + $second);
        }
    }


    public static function addMinute($minute, $start = null, $date_format = false)
    {
        return self::addSecond($minute * 60, $start, $date_format);
    }


    public static function addHour($hour, $start = null, $date_format = false)
    {
        return self::addSecond($hour * 3600, $start, $date_format);
    }


    public static function addDay($day, $start = null, $date_format = false)
    {
        return self::addSecond($day * 86400, $start, $date_format);
    }


    public static function yesterday($date_format = false)
    {
        return self::addDay(-1, null, $date_format);
    }


    public static function tomorrow($date_format = false)
    {
        return self::addDay(1, null, $date_format);
    }


    public static function lastDay($date_format = true, $start = null)
    {
        $stamp = self::stamp($start);
        $date = date('Y-m', $stamp) . '-' . date('t', $stamp);
        return $date_format === true ? $date : $date . ' ' . date('H:i:s', $stamp);
    }


    public static function division($dividend, $divisor)
    {
        if (!is_numeric($dividend) || !is_numeric($divisor) || (int)$divisor === 0) {
            return false;
        }

        $quotient = $dividend / $divisor;
        $remainder = $dividend % $divisor;

        return [ceil($quotient), $remainder, $quotient];
    }


    public static function sizes($size, $byte = 1024, $precision = 2)
    {
        if (!is_numeric($size) || !is_numeric($byte) || !is_numeric($precision)) {
            return '0 B';
        }

        $units = [' B', ' KB', ' MB', ' GB', ' TB', ' PB', ' EB', ' ZB', ' YB', ' BB'];
        for ($i = 0; $size >= $byte && $i < 9; $i++) {
            $size /= $byte;
        }
        return round($size, $precision) . $units[$i];
    }


    public static function str2arr($str, $split = 1, $encode = 'UTF-8')
    {
        $arr = [];
        if (!(self::isStr($str) && self::isPlus($split))) return $arr;

        $len = mb_strlen($str, $encode);
        $part = ceil($len / $split);

        for ($i = 0; $i < $part; $i++) {
            $arr[] = mb_substr($str, $i * $split, $split, $encode);
        }
        return $arr;
    }


    public static function shuffle($obj, $encode = 'UTF-8')
    {
        if (self::isStr($obj)) {
            $arr = self::str2arr($obj, 1, $encode);
            shuffle($arr);
            return implode('', $arr);
        } elseif (self::isArr($obj)) {
            shuffle($obj);
            return $obj;
        } else {
            return false;
        }
    }


    public static function unique($obj, $encode = 'UTF-8')
    {
        if (self::isArr($obj)) {
            return array_unique($obj);
        } elseif (is_string($obj)) {
            return implode('', array_unique(self::str2arr($obj, 1, $encode)));
        } else {
            return false;
        }
    }


    public static function reverse($obj, $encode = 'UTF-8')
    {
        if (self::isStr($obj)) {
            return implode('', array_reverse(self::str2arr($obj, 1, $encode)));
        } elseif (self::isArr($obj)) {
            return array_reverse($obj);
        } else {
            return false;
        }
    }


    public static function pos($obj, $pos, $encode = 'UTF-8')
    {
        if (!is_int($pos)) return false;

        if (self::isStr($obj)) {
            return mb_substr($obj, $pos, 1, $encode);
        } elseif (self::isArr($obj)) {
            return $obj[$pos];
        } else {
            return false;
        }
    }


    public static function ascii($rome = false, $shuffle = false)
    {
        $ascii = self::$_digit . self::$_character . strtoupper(self::$_character);

        if ($rome === 0) {
            $ascii .= self::$_rome;
        } elseif ($rome === 1) {
            $ascii .= mb_strtoupper(self::$_rome);
        } elseif ($rome == 2) {
            $ascii .= self::rome(1);
        } else {
            $ascii .= '';
        }

        return $shuffle === true ? self::shuffle($ascii) : $ascii;
    }


    public static function arrTrim($obj, $operation = 'trim', $search = '', $replace = '', $regex = false)
    {
        if (!self::isArr($obj)) return [];

        $operation = strtolower($operation);
        if (!in_array($operation, ['trim', 'ltrim', 'rtrim'])) return [];

        $regex = $regex === true ? 'preg_replace' : 'str_replace';

        $arr = [];
        if (self::isStr($search) && is_string($replace)) {
            foreach ($obj as $k => $v) {
                $v = call_user_func_array($operation, [$v]);
                $arr[$k] = call_user_func_array($regex, [$search, $replace, $v]);
                //yield $k => call_user_func_array($regex, [$search, $replace, $v]);
            }
        } else {
            foreach ($obj as $k => $v) {
                $arr[$k] = call_user_func_array($operation, [$v]);
                //yield $k => call_user_func_array($operation, [$v]);
            }
        }
        return $arr;
    }


    private static function _obfuscate($str)
    {
        $out = '';
        if (substr($str, -2) === '==') {
            $str = substr($str, 0, -2);
            $fix = '==';
        } elseif (substr($str, -1) === '=') {
            $str = substr($str, 0, -1);
            $fix = '=';
        } else {
            $fix = '';
        }

        $len = mb_strlen($str);

        $quotient = (int)floor($len / 2);
        $remainder = (int)($len % 2);

        if ($remainder === 0) {
            $s1 = mb_substr($str, 0, $quotient);
            $s2 = '';
            $s3 = mb_substr($str, $quotient);
        } else {
            $s1 = mb_substr($str, 0, $quotient);
            $s2 = mb_substr($str, $quotient, 1);
            $s3 = mb_substr($str, $quotient + 1);
        }

        if ($quotient >= 5) {
            $out .= mb_substr($s3, 0, 1) . self::reverse(mb_substr($s3, 1));
            $out .= $s2;
            $out .= mb_substr($s1, 0, 1) . self::reverse(mb_substr($s1, 1));
        } else {
            $out = self::reverse($s3) . $s2 . self::reverse($s1);
        }

        return $out . $fix;
    }


    public static function encode($str)
    {
        if (!self::isStr($str)) return false;
        return self::_obfuscate(base64_encode($str));
    }


    public static function decode($str)
    {
        if (!Validate::isBase64($str, false)) return false;
        return base64_decode(self::_obfuscate($str));
    }


    private static function _encAdd($random = false)
    {
        if ($random === true) {
            if (Validate::isLogin()) {
                if (!isset($_SESSION['time'])) {
                    $_SESSION['time'] = time();
                }
                return $_SESSION['time'];
            } else {
                return self::$_encode_start;
            }
        }
        return self::$_encode_start;
    }


    private static function _encBase($random = false)
    {
        if ($random === true) {
            if (Validate::isLogin()) {
                if (!isset($_SESSION['encode_id_base'])) {
                    $_SESSION['encode_id_base'] = mt_rand(30, 60);
                }
                return $_SESSION['encode_id_base'];
            } else {
                return self::$_encode_base;
            }
        }
        return self::$_encode_base;
    }


    public static function enc($id, $random = true)
    {
        return (new BaseConvert($id + self::_encAdd($random), 10, self::_encBase($random)))->convert();
    }


    public static function dec($id, $random = true)
    {
        return (new BaseConvert($id, self::_encBase($random), 10))->convert() - self::_encAdd($random);
    }


    public static function dirPath($dir, $unix_format = false)
    {
        if (!self::isStr($dir)) return false;

        if (!in_array(substr($dir, -1), ['\\', '/'])) {
            $dir .= DIRECTORY_SEPARATOR;
        }

        return $unix_format === true ? str_replace('\\', '/', $dir) : $dir;
    }


    public static function dirCopy($source, $target)
    {
        if (!is_dir($source) || !self::isStr($target)) return false;

        $source = self::dirPath($source);
        $target = self::dirPath($target);

        if (!is_dir($target)) {
            $md_status = mkdir($target, 0755, true);
            if (!$md_status) return false;
        }

        foreach (scandir($source) as $dir) {
            if ($dir != '.' && $dir != '..') {
                if (is_dir($source . $dir)) {
                    self::dirCopy($source . $dir, $target . $dir);
                } else {
                    copy($source . $dir, $target . $dir);
                }
            }
        }
        return true;
    }


    public static function dirDel($dir, $keep_self = false)
    {
        if (!is_dir($dir)) return false;

        $dir = self::dirPath($dir);
        $dl = [];

        foreach (scandir($dir) as $file) {
            if ($file != '.' && $file != '..') {
                if (is_dir($dir . $file)) {
                    self::dirDel($dir . $file);
                    $dl[] = $dir . $file;
                } else {
                    unlink($dir . $file);
                }
            }
        }

        foreach ($dl as $file) {
            if (is_dir($file)) rmdir($file);
        }

        if ($keep_self != true && is_dir($dir)) rmdir($dir);

        return true;
    }


    public static function dirList($dir, $method = true)
    {
        $dir = self::dirPath($dir);

        foreach (scandir($dir) as $file) {
            if ($file !== '.' && $file !== '..') {

                if (is_dir($dir . $file)) {
                    yield from self::dirList($dir . $file, $method);
                    if (is_bool($method)) {
                        yield $dir . $file;
                    }
                } else {
                    if ($method === true || is_int($method)) {
                        yield $dir . $file;
                    }

                }
            }
        }
    }


    public static function utf8Serialize($str)
    {
        return preg_replace_callback('#s:(\d+):"(.*?)";#s', function ($match) {
            return 's:' . strlen($match[2]) . ':"' . $match[2] . '";';
        }, $str);
    }


    public static function len($obj, $encode = 'ascii')
    {
        if (self::isStr($obj)) {
            return strtolower($encode) === 'ascii' ? strlen($obj) : mb_strlen($obj, $encode);
        } elseif (self::isArr($obj)) {
            return count($obj);
        } else {
            return 0;
        }
    }


    public static function spaceReplace($str, $replace = ' ')
    {
        return preg_replace('/[ ]+/', $replace, $str);
    }


    public static function uuid($capital = false, $split = '-', $brace = false)
    {
        $uuid = md5(uniqid(mt_rand(), true));
        $uuid = substr($uuid, 0, 8) . $split
            . substr($uuid, 8, 4) . $split
            . substr($uuid, 12, 4) . $split
            . substr($uuid, 16, 4) . $split
            . substr($uuid, 20, 12);

        if ($brace === true) {
            $uuid = '{' . $uuid . '}';
        } elseif (self::isArr($brace) && sizeof($brace) >= 2) {
            $uuid = $brace[0] . $uuid . $brace[1];
        } else {
            $uuid .= '';
        }

        return $capital === true ? strtoupper($uuid) : $uuid;
    }


    public static function ip()
    {
        //if (isset($_SERVER)) {
        //    if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        //        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        //    } elseif (isset($_SERVER['HTTP_CLIENT_IP'])) {
        //        $ip = $_SERVER['HTTP_CLIENT_IP'];
        //    } else {
        //        $ip = $_SERVER['REMOTE_ADDR'];
        //    }
        //} else {
        //    if (getenv("HTTP_X_FORWARDED_FOR")) {
        //        $ip = getenv("HTTP_X_FORWARDED_FOR");
        //    } elseif (getenv("HTTP_CLIENT_IP")) {
        //        $ip = getenv("HTTP_CLIENT_IP");
        //    } else {
        //        $ip = getenv("REMOTE_ADDR");
        //    }
        //}
        //return $ip ? $ip : '';
        return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown';
    }


    public static function uid($uid = 0)
    {
        if (self::isPlus($uid)) {
            return $uid;
        }
        return (isset($_SESSION['id']) && self::isPlus($_SESSION['id'])) ? $_SESSION['id'] : 0;
    }


    public static function microSecond($stamp = true, $cut = 2)
    {
        $_ms = explode(' ', microtime());
        $ms = $cut > 0 ? substr($_ms[0], 2, -$cut) : substr($_ms[0], 2);
        $st = $_ms[1];

        return $stamp === true ? (int)($st . $ms) : date('YmdHis') . $ms;
    }


    private static function _baseSku()
    {
        if (isset($_SESSION['base_sku']) && self::isStr($_SESSION['base_sku'])) {
            return $_SESSION['base_sku'];
        } elseif (defined('BASE_SKU') && self::isStr(BASE_SKU)) {
            return BASE_SKU;
        } else {
            return self::_obfuscate(self::digit() . self::upper());
        }
    }

    public static function sku($number = null, $len = 11)
    {
        if (!self::isPlus($len) || $len < 8) return false;
        if (self::isPlus($number)) {
            $number += pow(36, $len - 1);
        } else {
            $number = self::microSecond() + pow(36, $len - 1);
        }

        return (new BaseConvert($number, 10, 36, self::_baseSku()))->convert();
    }


    private static function _toStr($str)
    {
        if (is_array($str)) {
            return serialize($str);
        } elseif (is_string($str)) {
            return $str;
        } else {
            return (string)$str;
        }
    }


    public static function debug($str)
    {
        $log = APP_PATH . 'var/log/debug-' . self::today() . '.txt';
        if (APP_DEBUG) {
            file_put_contents($log, self::now() . "\t" . self::_toStr($str) . "\r\n", FILE_APPEND);
        }
    }

    /*




    private static function _conf($conf, $str, $part = 1)
    {
        switch ($part) {
            case 0:
                $fix = '';
                break;
            case 1:
                $fix = '    ';
                break;
            case 2:
                $fix = '        ';
                break;
            default:
                $fix = '';
                break;
        }
        file_put_contents($conf, $fix . $str . PHP_EOL, FILE_APPEND);
    }

    public static function nginx($domain, $root, $server)
    {
        $conf = APP_PATH . '../.nginx_conf/' . $domain . '.conf';

        if (!is_file($conf)) {
            $domain .= Validate::isDomain($domain, true) ? ' www.' . $domain : '';

            file_put_contents($conf, 'server{' . PHP_EOL);
            self::_conf($conf, 'listen ' . $server . ':80;');
            self::_conf($conf, 'server_name ' . $domain . ';');
            self::_conf($conf, 'root ' . $root . ';');
            self::_conf($conf, 'index index.php index.html index.htm;');

            self::_conf($conf, '', 0);

            self::_conf($conf, 'location ~ .*\.(js|css|jpg|png|webp|woff|otf)$ {');
            self::_conf($conf, 'expires 10d;', 2);
            self::_conf($conf, '}');

            self::_conf($conf, '', 0);

            self::_conf($conf, 'error_page 404 /404.html;');
            self::_conf($conf, 'error_page 500 502 503 504 /50x.html;');

            file_put_contents($conf, '}' . PHP_EOL, FILE_APPEND);
        }
    }


    public static function permission($arr)
    {
        $val = [];
        foreach ($arr as $k => $v) {
            if (isset($_SESSION['permission'][$k])) {
                $val[$k] = $_SESSION['permission'][$k] + $v;
            } else {
                $val[$k] = $v;
            }
        }

        foreach ($_SESSION['permission'] as $k => $v) {
            if (!isset($val[$k])) {
                $val[$k] = $v;
            }
        }

        return $val;
    }


    public static function access($action, $node)
    {
        return isset($_SESSION['permission'][$node]) && in_array($action, $_SESSION['permission'][$node]);
    }

    public static function uDir($uid = 0)
    {
        return self::encInt(self::uid($uid), false);
    }

    public static function initDir($aid, $pid = 0, $uid = 0, $sid = null)
    {
        $ud = Base::encInt(Base::uid($uid), false);
        $sd = is_int($sid) ? Base::encInt($sid, false) : '';
        $pd = Base::encInt($pid, false);
        $ad = Base::encInt($aid, false);

        $dir = $sd ? $ud . '/' . $sd : $ud;
        $dir .= '/' . $pd . '/' . $ad . '/';

        $res_dir = APP_PATH . 'data/' . $dir . '/';
        $http_dir = APP_PATH . 'public/data/' . $dir . '/';

        if (!is_dir($res_dir)) mkdir($res_dir, 0755, true);
        if (!is_dir($http_dir)) mkdir($http_dir, 0755, true);
        return is_dir($res_dir) && is_dir($http_dir);
    }


    public static function siteDir($sid, $uid = 0, $real = false)
    {
        $dir = self::encInt(self::uid($uid), false) . '/' . self::encInt($sid, false) . '/';

        return $real === true ? APP_PATH . 'public/data/' . $dir : $dir;
    }


    public static function siteRoot($sid, $uid = 0, $real = false)
    {
        $path = APP_PATH . 'public/data/' . self::encInt(self::uid($uid), false) . '/' .
            self::encInt($sid, false) . '/';

        return $real === true ? realpath($path) : $path;
    }


    public static function microSecond($convert = false, $stamp = true, $cut = 2)
    {
        $_ms = explode(' ', microtime());
        $ms = $cut > 0 ? substr($_ms[0], 2, -$cut) : substr($_ms[0], 2);
        $st = $_ms[1];


        return $convert === true ?
            (new BaseConvert((int)($st . $ms), 10, 36))->convert() :
            ($stamp === true ? (int)($st . $ms) : date('YmdHis') . $ms);
    }

    public static function sku($number = null, $len = 11)
    {
        if (!self::isPlus($len)) return false;

        if (self::isPlus($number)) {
            $number += pow(36, $len - 1);
        } else {
            $number = self::microSecond(false) + pow(36, $len - 1);
        }

        if (!self::isPlus($number)) return false;
        if (!defined('BASE_SKU')) return false;
        return (new BaseConvert($number, 10, 36, BASE_SKU))->convert();
    }

    */
}