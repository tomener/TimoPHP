<?php
/**
 * TimoPHP a Fast Simple Smart PHP FrameWork
 * Author: Tommy 863758705@qq.com
 * Link: http://www.TimoPHP.com/
 * Since: 2016
 */

namespace Timo;

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
     * 创建文件夹[弃用，请使用File::mkDir]
     *
     * @param string $path
     * @param int $mode
     */
    static function mkFolders($path, $mode = 0755)
    {
        if (!is_dir($path)) {
            mkdir($path, $mode, true);
        }
    }

    /**
     * 根据文件名创建文件[弃用，请使用File::mkFile]
     *
     * @param string $file_name
     * @param int $mode
     * @return bool
     */
    static function mkFile($file_name, $mode = 0775)
    {
        if (!file_exists($file_name)) {
            $file_path = dirname($file_name);
            static::mkFolders($file_path, $mode);

            $fp = fopen($file_name, 'w+');
            if ($fp) {
                fclose($fp);
                chmod($file_name, $mode);
                return true;
            }
            return false;
        }
        return true;
    }

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
     * 生成四层亿级路径
     *
     * @param int $id
     * @param string $path_name
     * @return string
     *
     * 例: id = 1000189 生成 001/00/01/89
     */
    static function getFourPath($id, $path_name = '')
    {
        $id = (string)abs($id);
        $id = str_pad($id, 9, '0', STR_PAD_LEFT);
        $dir1 = substr($id, 0, 3);
        $dir2 = substr($id, 3, 2);
        $dir3 = substr($id, 5, 2);

        return ($path_name ? $path_name . '/' : '') . $dir1 . '/' . $dir2 . '/' . $dir3 . '/' . substr($id, -2) . '/';
    }

    /**
     * 获取文件扩展名[弃用，请使用File::ext]
     *
     * @param string $filename 文件名
     * @return string
     */
    static function getFileExt($filename)
    {
        $file_info = pathinfo($filename);
        return $file_info['extension'];
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
