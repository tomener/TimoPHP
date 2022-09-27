<?php
/**
 * TimoPHP a Fast Simple Smart PHP FrameWork
 * Author: Tommy 863758705@qq.com
 * Link: http://www.TimoPHP.com/
 * Since: 2016
 */

namespace Timo\Helper;

use Timo\Core\Request;

/**
 * 助手类
 *
 * Class Helper
 * @package Timo
 */
class Helper
{
    /**
     * 获取文件路径
     *
     * @param $file_str
     * @return string
     */
    public static function path($file_str)
    {
        $map = [
            'root' => ROOT_PATH,
            'app' => APP_DIR_PATH,
            'cache' => CACHE_PATH,
            'storage' => ROOT_PATH . 'storage' . DIRECTORY_SEPARATOR,
            'tmp' => ROOT_PATH . 'tmp' . DIRECTORY_SEPARATOR,
            'static' => Request::getInstance()->getScriptFilePath() . DIRECTORY_SEPARATOR . 'static' . DIRECTORY_SEPARATOR
        ];

        list($path, $file) = explode('::', $file_str);

        if (!$path) {
            $path = 'root';
        }

        return $map[$path] . str_replace('/', DIRECTORY_SEPARATOR, $file);
    }

    /**
     * 返回一个指定长度的随机数
     *
     * @param int $length
     * @param bool $numeric
     * @return string
     */
    public static function random($length, $numeric = false)
    {
        $seed = md5(print_r($_SERVER, 1) . microtime(true));
        if ($numeric) {
            $seed = str_replace('0', '', base_convert($seed, 16, 10)) . '0123456789';
        } else {
            $seed = base_convert($seed, 16, 35) . 'zZz' . strtoupper($seed);
        }

        $hash = '';
        $max = strlen($seed) - 1;
        for ($i = 0; $i < $length; $i++) {
            $hash .= $seed[mt_rand(0, $max)];
        }

        return $hash;
    }

    /**
     * 生成随机16进制数
     *
     * @param $length
     * @return string
     */
    public static function randomHex($length)
    {
        $str = '0123456789abcdef';
        $random = '';
        for ($i = 0; $i < $length; $i++) {
            $random .= $str[mt_rand(0, 15)];
        }
        return $random;
    }
}
