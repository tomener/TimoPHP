<?php
/**
 * TimoPHP a Fast Simple Smart PHP FrameWork
 * Author: Tommy 863758705@qq.com
 * Link: http://www.TimoPHP.com/
 * Since: 2016
 */

namespace Timo\Orm;


/**
 * Class Db
 * @package Timo\Orm
 * @method static Query table(string $name) 指定数据表（全称）
 * @method static Query name(string $name) 指定数据表（不带前缀）
 * @method static array query(string $sql, $params = null, $row_type = null, $mode = \PDO::FETCH_ASSOC)
 * @method static array queryOne(string $sql, $params = null, $mode = \PDO::FETCH_ASSOC)
 * @method static bool execute(string $sql, $params = null) 执行非查询SQL语句
 */
class Db
{
    /**
     * 获取数据库连接
     *
     * @param $conf string|array 数据库名称|配置
     * @param array $options
     * @return Connection
     */
    public static function connect($conf = 'default', $options = [])
    {
        $connection = Connection::instance($conf, $options);
        return $connection;
    }

    public static function __callStatic($name, $arguments)
    {
        $connection = static::connect();
        return call_user_func_array([$connection, $name], $arguments);
    }
}
