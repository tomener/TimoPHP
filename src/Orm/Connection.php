<?php
/**
 * TimoPHP a Fast Simple Smart PHP FrameWork
 * Author: Tommy 863758705@qq.com
 * Link: http://www.TimoPHP.com/
 * Since: 2016
 */

namespace Timo\Orm;


use Exception;
use PDO;
use Timo\Config\Config;

class Connection
{
    /**
     * @var array 数据库连接池
     */
    protected static $instances = [];

    /**
     * @var array PDO对象池
     */
    protected static $pdos = [];

    /**
     * @var PDO 当前数据库连接对象
     */
    protected $pdo = null;

    /**
     * @var \PDOStatement 执行SQL语句后的返回对象
     */
    protected $stmt = null;

    /**
     * @var array 数据库配置
     */
    protected $config = [];

    /**
     * @var bool 是否启用读写分离
     */
    protected $rwSeparate = false;

    /**
     * @var string 当前执行的SQL语句
     */
    protected $sql = '';

    /**
     * @var string 数据库名
     */
    protected $database = '';

    /**
     * @var string 数据表名前缀
     */
    protected $prefix = null;

    /**
     * @var array 数据表信息
     */
    protected $tables = [];

    /**
     * 获取数据库连接实例
     *
     * @param $conf string|array 数据库名称|配置
     * @param array $options
     * @return Connection
     */
    public static function instance($conf, $options = [])
    {
        if (!is_array($conf)) {
            $conf = Config::runtime('mysql.' . $conf);
        }
        $name = md5(serialize($conf) . serialize($options));
        if (!isset(self::$instances[$name])) {
            $connection = new static();
            $connection->pdo = self::connect($conf, $options);
            $connection->rwSeparate = isset($conf['rw_separate']) ? $conf['rw_separate'] : false;
            $connection->config = $conf;
            $connection->database = $conf['database'];
            $connection->prefix = $conf['prefix'];
            self::$instances[$name] = $connection;
        }
        return self::$instances[$name];
    }

    /**
     * 连接数据库
     *
     * @param array $conf
     * @param array $options
     * @return PDO
     * @throws Exception
     */
    public static function connect(array $conf, $options = [])
    {
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $conf['host'], $conf['port'], $conf['database'], $conf['charset']);
        $name = md5(serialize($conf) . serialize($options));

        if (isset(self::$pdos[$name]) && is_a(self::$pdos[$name], 'PDO')) {
            return self::$pdos[$name];
        }

        $conf += [
            'options' => [],
            'persistence' => false,
            'user' => null,
            'password' => null
        ];

        //数据库连接
        try {
            $options += $conf['options'] + [
                    PDO::ATTR_PERSISTENT => $conf['persistence'],
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::ATTR_STRINGIFY_FETCHES => false,
                ];

            //实例化数据库连接
            $conn = new PDO($dsn, $conf['user'], $conf['password'], $options);
            self::$pdos[$name] = $conn;

        } catch (\PDOException $exception) {
            //抛出异常信息
            throw new Exception('Database connect error: ' . $exception->getMessage() . ' code: ' . $exception->getCode(), 60002);
        }
        return $conn;
    }

    /**
     * 获取数据库的配置参数
     *
     * @param string $config
     * @return array|mixed
     */
    public function getConfig($config = '')
    {
        return $config ? $this->config[$config] : $this->config;
    }

    /**
     * @return PDO
     */
    public function getPdo()
    {
        return $this->pdo;
    }

    /**
     * @param $table
     * @return Query
     */
    public function table($table)
    {
        $query = new Query($this);
        return $query->table($table);
    }

    /**
     * 设置表名（不带前缀）
     *
     * @param $table
     * @param $is_full
     * @return Query
     */
    public function name($table)
    {
        $query = new Query($this);
        return $query->table($this->prefix . $table);
    }

    /**
     * 获取一行
     *
     * @param Query $query
     * @param string $select
     * @return array
     * @throws Exception
     */
    public function row(Query $query, string $select = '')
    {
        if (!empty($select)) {
            $query->qos['select'] = $select;
        }
        $query->qos['limit'] = 1;
        $this->buildQuery($query);
        $ret = $this->query($query->qos['sql'], $query->qos['params'], 'one', $query->qos['mode']);
        return $ret;
    }

    /**
     * 获取一列
     *
     * @param string $select
     * @param string $key
     * @return array
     */
    public function column(Query $query, $select, $key = null)
    {
        $query->qos['select'] = $select;
        $count = 0;
        $column = null;
        if ($select != '*') {
            $field_arr = explode(',', $select);
            $count = count($field_arr);
            if (!is_null($key) && $count == 1) {
                $column = $select;
            }
            if (!is_null($key) && !in_array($key, $field_arr)) {
                array_unshift($field_arr, $key);
                $count++;
            }
            $query->qos['select'] = implode(',', $field_arr);
        }

        $this->buildQuery($query);
        $this->_execute($query->qos['sql'], $query->qos['params']);
        if ($count == 1) {
            $rows = $this->stmt->fetchAll(PDO::FETCH_COLUMN);
        } else {
            $rows = array_column($this->stmt->fetchAll(PDO::FETCH_ASSOC), $column, $key);
        }
        return $rows;
    }

    /**
     * 获取字段值
     *
     * @param string $field
     * @return int|string|false|null
     */
    public function value(Query $query, $field = '')
    {
        if (!empty($field)) {
            $query->qos['select'] = $field;
        }

        $query->qos['limit'] = 1;
        $this->buildQuery($query);

        $this->_execute($query->qos['sql'], $query->qos['params'], true);
        if (!$this->stmt) {
            return null;
        }

        $value = $this->stmt->fetchColumn();
        return $value;
    }

    /**
     * 获取多列
     *
     * @param $need_page bool
     * @return array
     */
    public function list(Query $query, $need_page = true)
    {
        $this->buildQuery($query);

        if (!empty($query->qos['page'])) {
            if ($need_page) {
                $table = self::getFormatTable($query);
                if (empty($query->qos['groupBy'])) {
                    $sql = 'SELECT COUNT(*) as total FROM ' . $table . $query->qos['join'] . $query->qos['condition'] . ' LIMIT 1';
                } else {
                    $sql = 'SELECT COUNT(*) as total FROM (SELECT count(*) FROM ' . $table . $query->qos['join'] . $query->qos['condition'] . $query->qos['groupBy'] . ') c LIMIT 1';
                }
                $total = (int)$this->query($sql, $query->qos['params'], 'one')['total'];
                $query->qos['page']['total'] = $total;
                $query->qos['page']['total_page'] = ceil($query->qos['page']['total'] / $query->qos['page']['limit']);
            } else {
                $query->qos['page']['total'] = 0;
                $query->qos['page']['total_page'] = 0;
            }
        }

        $rows = $this->query($query->qos['sql'], $query->qos['params'], 'all');
        return $rows;
    }

    /**
     * 自增
     *
     * @param string $field
     * @param int $step
     * @return bool|int
     */
    public function inc(Query $query, $field, $step = 1)
    {
        $data = [$field => ['+', $step]];
        return $this->update($query, $data);
    }

    /**
     * 自减
     *
     * @param string $field
     * @param int $step
     * @return bool|int
     */
    public function dec(Query $query, $field, $step = 1)
    {
        $data = [$field => ['-', $step]];
        return $this->update($query, $data);
    }

    /**
     * 统计条数
     *
     * @param $field
     * @return int
     */
    public function count(Query $query, $field = '*')
    {
        return (int)$this->converge($query, 'COUNT', $field);
    }

    /**
     * 求和
     *
     * @param $field
     * @return int|mixed
     */
    public function sum(Query $query, $field)
    {
        return $this->converge($query, 'SUM', $field);
    }

    /**
     * 求平均值
     *
     * @param $field
     * @return int|mixed
     */
    public function avg(Query $query, $field)
    {
        return $this->converge($query, 'AVG', $field);
    }

    /**
     * 求最大值
     *
     * @param $field
     * @return int|mixed
     */
    public function max(Query $query, $field)
    {
        return $this->converge($query, 'MAX', $field);
    }

    /**
     * 求最小值
     *
     * @param $field
     * @return int|mixed
     */
    public function min(Query $query, $field)
    {
        return $this->converge($query, 'MIN', $field);
    }

    /**
     * 聚合统计
     *
     * @param $type
     * @param $field
     * @return int|mixed
     */
    protected function converge(Query $query, $type, $field)
    {
        $query->qos['select'] = $type . '(' . $field . ') ret';
        $row = $this->row($query);
        return $row['ret'] ? $row['ret'] : 0;
    }

    /**
     * 组装SQL语句
     *
     * @return string
     * @throws Exception
     */
    public static function buildQuery(Query $query)
    {
        if (empty($query->qos['table'])) {
            throw new Exception('not set table in db query build');
        }
        $table = self::getFormatTable($query);

        $sql = 'SELECT ' . $query->qos['select'] . ' FROM ' . $table;
        if (!empty($query->qos['join'])) {
            $sql .= $query->qos['join'];
        }

        $query->qos['condition'] = !empty($query->qos['where']) ? ' WHERE ' . self::buildWhere($query->qos['where'], $query->qos['params']) : '';
        $sql .= $query->qos['condition'];

        if (!empty($query->qos['groupBy'])) {
            $sql .= ' GROUP BY ' . $query->qos['groupBy'];
        }
        if (!empty($query->qos['having'])) {
            $sql .= ' HAVING ' . $query->qos['having'];
        }
        if (!empty($query->qos['orderBy'])) {
            $sql .= ' ORDER BY ' . $query->qos['orderBy'];
        }
        if (!empty($query->qos['limit'])) {
            $sql .= ' LIMIT ' . $query->qos['limit'];
        }
        if ($query->qos['forUpdate']) {
            $sql .= ' FOR UPDATE';
        }

        $query->qos['sql'] = $sql;
        $query->connection()->sql = $sql;

        return $sql;
    }

    /**
     * 组装where条件
     *
     * @param $where
     * @param array $params
     * @return string
     */
    public static function buildWhere($where, array &$params)
    {
        $condition = '';
        foreach ($where as $item) {
            if (is_string($item)) {
                $condition .= $item;
                continue;
            } elseif ($item instanceof \Closure) {
                $condition .= self::buildWhere(self::parseWhere($item()), $params);
                continue;
            }
            if ($item['column'] == '_string') {
                $condition .= sprintf(' %s %s', $item['logic'], $item['value']);
                continue;
            }
            if (strpos($item['column'], '.') === false) {
                $item['column'] = sprintf('`%s`', $item['column']);
            } else {
                $arr = explode('.', $item['column']);
                $item['column'] = sprintf('`%s`.%s', $arr[0], $arr[1]);
            }
            switch ($item['operator']) {
                case '=':
                case '<':
                case '<=':
                case '>':
                case '>=':
                case '<>':
                case 'like':
                    $condition .= sprintf(' %s %s %s ?', $item['logic'], $item['column'], $item['operator']);
                    array_push($params, $item['value']);
                    break;
                case 'between':
                    $condition .= sprintf(' %s %s BETWEEN ? AND ?', $item['logic'], $item['column']);
                    $params = array_merge($params, $item['value']);
                    break;
                case 'in':
                    $item['value'] = is_array($item['value']) ? "'" . implode("','", $item['value']) . "'" : $item['value'];
                    $condition .= sprintf(" %s %s IN(" . $item['value'] . ")", $item['logic'], $item['column']);
                    break;
                case 'find_in_set':
                    $condition .= sprintf(' %s FIND_IN_SET(?, %s)', $item['logic'], $item['column']);
                    array_push($params, $item['value']);
                    break;
            }
        }

        return trim($condition, 'AND ');
    }

    /**
     * 解析where条件
     *
     * @param $column
     * @param null $operator
     * @param null $value
     * @param string $logic
     * @return array
     */
    public static function parseWhere($column, $operator = null, $value = null, $logic = 'AND')
    {
        $where = [];
        if (is_array($column)) {
            if (key($column) === 0) { //索引数组
                foreach ($column as $key => $item) {
                    if (count($item) == 2) {
                        $operator = '=';
                        $value = $item[1];
                    } else {
                        $operator = $item[1];
                        $value = $item[2];
                    }
                    $where[] = ['column' => $item[0], 'operator' => $operator, 'value' => $value, 'logic' => $logic];
                }
            } else { //关联数组
                foreach ($column as $key => $value) {
                    $_logic = $logic;
                    $operator = '=';
                    if (is_array($value)) {
                        if ($value['0'] == 'OR' || $value[0] == 'AND') {
                            $_logic = array_shift($value);
                        }
                        $operator = $value[0];
                        if (count($value) == 2) {
                            $value = $value[1];
                        } else {
                            $value = array_slice($value, 1);
                        }
                    }
                    $where[] = ['column' => $key, 'operator' => $operator, 'value' => $value, 'logic' => $_logic];
                }
            }
        } else {
            if ($value === null) {
                $value = $operator;
                $operator = '=';
            }
            $where[] = ['column' => $column, 'operator' => $operator, 'value' => $value, 'logic' => $logic];
        }
        return $where;
    }

    /**
     * 执行查询语句
     *
     * @param string $sql
     * @param array $params
     * @param string $row_type
     * @param int $mode
     * @return array
     */
    public function query($sql, $params = null, $row_type = null, $mode = PDO::FETCH_ASSOC)
    {
        $this->_execute($sql, $params, true);
        if (!$this->stmt) {
            return [];
        }

        if ($row_type == 'one') {
            $rows = $this->stmt->fetch($mode);
        } else {
            $rows = $this->stmt->fetchAll($mode);
        }

        $this->stmt->closeCursor();
        return $rows ? $rows : [];
    }

    /**
     * 查询一条记录
     *
     * @param $sql
     * @param null $params
     * @param int $mode
     * @return array
     */
    public function queryOne($sql, $params = null, $mode = PDO::FETCH_ASSOC)
    {
        return $this->query($sql, $params, 'one', $mode);
    }

    /**
     * 执行非查询SQL语句
     *
     * @param $sql
     * @param null $params
     * @return bool
     */
    public function execute($sql, $params = null)
    {
        $this->_execute($sql, $params);
        if (!$this->stmt) {
            return false;
        }

        return true;
    }

    /**
     * 插入单条数据
     *
     * @param array $data
     * @return bool|string
     */
    public function insert(Query $query, array $data)
    {
        $table = $query->qos['table'];
        if (empty($table) || empty($data)) {
            return false;
        }

        $this->filterFields($table, $data);
        $params = array_values($data);

        $fields = '`' . implode('`,`', array_keys($data)) . '`';
        $values = rtrim(str_repeat('?,', count($params)), ',');

        $sql = sprintf('INSERT INTO `%s` (%s) VALUES (%s)', $table, $fields, $values);

        $ret = $this->execute($sql, $params);
        if (!$ret) {
            return false;
        }

        if ($this->getPkAutoIncrement($table)) {
            return $this->lastInsertId();
        } else {
            return $data[$this->getPrimaryKey($table)];
        }
    }

    /**
     * 批量插入数据
     *
     * @param Query $query
     * @param $data
     * @param bool $returnId
     * @return bool|string
     */
    public function insertList(Query $query, $data, $returnId = false)
    {
        $table = $query->qos['table'];
        if (empty($table) || empty($data) || !isset($data[0]) || !is_array($data[0])) {
            return false;
        }

        $fields = '`' . implode('`,`', array_keys($data[0])) . '`';

        $values = '(' . rtrim(str_repeat('?,', count($data[0])), ',') . ')';
        $values = rtrim(str_repeat($values . ',', count($data)), ',');

        $params = [];
        foreach ($data as $item) {
            $params = array_merge($params, array_values($item));
        }

        //组装SQL语句
        $sql = sprintf('INSERT INTO `%s` (%s) VALUES %s', $table, $fields, $values);

        $result = $this->execute($sql, $params);

        //当返回数据需要返回insert id时
        if ($result && $returnId === true) {
            return $this->lastInsertId();
        }

        return $result;
    }

    /**
     * 数据表更新操作
     *
     * @param array $data
     * @return bool|int
     * @throws Exception
     */
    public function update(Query $query, array $data)
    {
        if (empty($query->qos['table'])) {
            throw new \Exception('db update need appoint table name');
        }
        if (empty($query->qos['where'])) {
            throw new Exception('db update need where condition');
        }
        if (empty($data)) {
            throw new \Exception('db update need data not empty');
        }

        $this->filterFields($query->qos['table'], $data);
        $updateString = '';
        $update_params = [];
        foreach ($data as $key => $val) {
            if (!is_array($val)) {
                $updateString .= '`' . $key . '` = ?,';
                $update_params[] = $val;
            } else {
                $pl = count($val);
                if ($val[0] == 'raw') {
                    $updateString .= '`' . $key . '` = ' . $val[1] . ',';
                } elseif ($pl == 2) {
                    $updateString .= '`' . $key . '` = ' . $key . $val[0] . '?,';
                    $update_params[] = $val[1];
                } else {
                    $updateString .= '`' . $key . '` = ' . $val[0] . $val[1] . $val[2] . ',';
                }
            }
        }
        $updateString = rtrim($updateString, ',');

        $params = [];
        $where = $this->buildWhere($query->qos['where'], $params);
        $params = array_merge($update_params, $params);

        $sql = sprintf("UPDATE `%s` SET %s WHERE %s", $query->qos['table'], $updateString, $where);
        $ret = $this->execute($sql, $params);
        if ($ret) {
            return $this->stmt->rowCount();
        }
        return $ret;
    }

    /**
     * 数据表删除操作
     *
     * @param Query $query
     * @return bool|int
     * @throws Exception
     */
    public function delete(Query $query)
    {
        if (empty($query->qos['table'])) {
            throw new Exception('db operation need appoint table name');
        }
        if (empty($query->qos['where'])) {
            throw new Exception('db operation need where condition');
        }

        $params = [];
        $where = $this->buildWhere($query->qos['where'], $params);

        $sql = sprintf("DELETE FROM `%s` WHERE %s", $query->qos['table'], $where);
        $ret = $this->execute($sql, $params);
        if ($ret) {
            return $this->stmt->rowCount();
        }
        return $ret;
    }

    /**
     * 开启事务处理
     *
     * @return boolean
     */
    public function startTrans()
    {
        if (!$this->pdo->inTransaction()) {
            $this->pdo->beginTransaction();
        }
        return true;
    }

    /**
     * 提交事务处理
     *
     * @return boolean
     */
    public function commit()
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->commit();
        }
        return true;
    }

    /**
     * 事务回滚
     *
     * @return boolean
     */
    public function rollback()
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
        return true;
    }

    /**
     * 获取格式化后表名
     *
     * @param Query $query
     * @return string
     */
    public static function getFormatTable(Query $query)
    {
        $table = $query->qos['table'];
        if (strpos($table, ' ') === false) {
            $table = '`' . $table . '`';
        }
        if (!empty($query->qos['alias'])) {
            $table .= ' `' . $query->qos['alias'] . '`';
        }

        return $table;
    }

    /**
     * 过虑数据表字段信息
     *
     * @param string $table 表名
     * 用于insert|update里的字段信息进行过虑，删除掉非法的字段信息。
     * @param array $data
     */
    protected function filterFields($table, array &$data = [])
    {
        //获取数据表字段
        $tableFields = $this->getTableFields($table);

        foreach ($data as $key => $value) {
            if (!in_array($key, $tableFields)) {
                unset($data[$key]);
            }
        }
    }

    /**
     * 获取数据表主键
     *
     * @param $table
     * @return string
     */
    public function getPrimaryKey($table)
    {
        $this->loadTableCache($table);
        return $this->tables[$table]['primaryKey'];
    }

    /**
     * 获取主键是否自增
     *
     * @return bool
     */
    public function getPkAutoIncrement($table)
    {
        $this->loadTableCache($table);
        return $this->tables[$table]['pkAutoIncrement'];
    }

    /**
     * 获取表字段信息
     *
     * @return array
     */
    public function getTableFields($table)
    {
        $this->loadTableCache($table);
        return $this->tables[$table]['fields'];
    }

    /**
     * 创建表信息缓存
     *
     * @param string $table 数据表名称
     * @return bool
     */
    protected function loadTableCache($table)
    {
        //参数分析
        if (!$table) {
            return false;
        }

        if (isset($this->tables[$table])) {
            return true;
        }

        $cacheFile = $this->getCacheFile($table);
        if (is_file($cacheFile)) {
            $this->tables[$table] = include $cacheFile;
            return true;
        }

        //获取数据表字段信息
        $tableInfo = $this->getTableInfo($table);
        $this->tables[$table] = [
            'primaryKey' => $tableInfo['primaryKey'][0],
            'pkAutoIncrement' => $tableInfo['pkAutoIncrement'],
            'fields' => $tableInfo['fields'],
        ];

        if (APP_DEBUG || IS_CLI) {
            return true;
        }

        //缓存文件内容
        $cacheContent = "<?php\nreturn " . var_export($this->tables[$table], true) . ";";

        //分析缓存目录
        $cacheDir = dirname($cacheFile);
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0777, true);
        }

        //将缓存内容写入缓存文件
        file_put_contents($cacheFile, $cacheContent, LOCK_EX);

        return true;
    }

    /**
     * 删除表缓存文件
     *
     * @param string $tableName 数据表名
     * @return boolean
     */
    public function removeCache($tableName)
    {
        $cacheFile = $this->getCacheFile($tableName);
        if (!is_file($cacheFile)) {
            return true;
        }
        return unlink($cacheFile);
    }

    /**
     * 获取表缓存文件的路径
     *
     * @param string $table 数据表名
     * @return string 缓存文件的路径
     */
    protected function getCacheFile($table)
    {
        return CACHE_PATH . 'models' . DS . $this->database . DS . $table . '.cache.php';
    }

    /**
     * 根据数据表名获取该数据表的字段信息
     *
     * @param string $tableName 数据表名
     * @param boolean $extItem 数据返回类型选项，即是否返回完成的信息(包含扩展信息)。true:含扩展信息/false:不含扩展信息
     * @return array|bool
     */
    public function getTableInfo($tableName, $extItem = false)
    {
        //参数分析
        if (!$tableName) {
            return false;
        }

        $fieldList = $this->query("SHOW FIELDS FROM `{$tableName}`");
        if ($extItem === true) {
            return $fieldList;
        }

        //过滤掉杂数据
        $primaryArray = [];
        $pkAutoIncrement = 0;
        $fieldArray = [];

        foreach ($fieldList as $line) {
            //分析主键
            if ($line['Key'] == 'PRI') {
                $primaryArray[] = $line['Field'];
                if ($line['Extra'] == 'auto_increment') {
                    $pkAutoIncrement = 1;
                }
            }
            //分析字段
            $fieldArray[] = $line['Field'];
        }

        return ['primaryKey' => $primaryArray, 'pkAutoIncrement' => $pkAutoIncrement, 'fields' => $fieldArray];
    }

    /**
     * 获取当前数据库中的所有的数据表名的列表
     *
     * @return array
     */
    public function getTableList()
    {
        //执行SQL语句，获取数据信息
        $tableList = $this->query("SHOW TABLES", null, null, PDO::FETCH_COLUMN);
        if (!$tableList) {
            return [];
        }

        return array_values($tableList);
    }

    /**
     * 获取最新的insert_id
     *
     * @return string
     */
    public function lastInsertId()
    {
        return $this->pdo->lastInsertId();
    }

    public function getLastSql()
    {
        return $this->sql;
    }

    /**
     * 检测连接是否可用
     *
     * @return bool
     */
    public function ping()
    {
        try {
            $this->pdo->getAttribute(PDO::ATTR_SERVER_INFO);
        } catch (\PDOException $e) {
            return false;
        }
        return true;
    }

    /**
     * 对字符串进行转义,提高数据库操作安全
     *
     * @param null $value 待转义的字符串内容
     * @return array|string|null
     */
    public function escape($value = null)
    {
        //参数分析
        if (is_null($value)) {
            return null;
        }

        if (!is_array($value)) {
            return trim($this->pdo->quote($value));
        }

        //当参数为数组时
        return array_map([$this, 'escape'], $value);
    }

    /**
     * 获取执行SQL语句的返回结果$stmt
     *
     * @param string $sql SQL语句内容
     * @param array $params 待转义的参数值
     * @param bool $readonly 是读还是写
     *
     * @return bool
     */
    protected function _execute($sql, array $params = null, $readonly = false)
    {
        $this->sql = $this->_parseQuerySql($sql, $params);

        $pdo = $this->pdo;

        if ($readonly && $this->rwSeparate === true) {
            $slave_config = $this->config['slave'];
            $slave_key = array_rand($slave_config);
            $pdo = self::connect($slave_config[$slave_key]);
        }

        try {
            //执行SQL语句
            $this->stmt = $pdo->prepare($sql);
            $result = $this->stmt->execute($params);

            //分析执行结果
            if (!$result) {
                $this->stmt->closeCursor();
                return false;
            }

            return true;
        } catch (\PDOException $e) {

            //抛出异常信息
            $this->throwException($e, $sql, $params);
            return false;
        }
    }

    /**
     * 分析组装所执行的SQL语句
     * 用于prepare()与execute()组合使用时，组装所执行的SQL语句
     *
     * @param string $sql SQL语句
     * @param array $params 参数值
     *
     * @return string
     */
    protected function _parseQuerySql($sql, array $params = null)
    {
        if (!$sql) {
            return false;
        }
        $sql = trim($sql);

        //当所要转义的参数值为空时
        if (!$params) {
            return $sql;
        }

        foreach ($params as &$param) {
            if (is_string($param)) {
                $param = sprintf("'%s'", $param);
            }
        }

        $sql = str_replace('?', '%s', $sql);
        return vsprintf($sql, $params);
    }

    /**
     * 抛出异常提示信息处理
     * 用于执行SQL语句时，程序出现异常时的异常信息抛出
     *
     * @param $exception \PDOException
     * @param $sql
     * @param array $params
     * @return bool
     * @throws Exception
     */
    protected function throwException(\PDOException $exception, $sql, $params = [])
    {
        //参数分析
        if (!is_object($exception) || !$sql) {
            return false;
        }

        if (strpos($exception->getMessage(), 'MySQL server has gone away')) {
            $code = 60004;
        } else {
            $code = $exception->getCode();
        }
        if (!is_numeric($code)) {
            $code = 60001;
        }
        $sql = $this->_parseQuerySql($sql, $params);
        $message = 'SQL execute error: ' . $sql . ' |' . $exception->getMessage() . ' Code: ' . $exception->getCode();

        //抛出异常信息
        throw new Exception($message, $code);
    }

    /**
     * 销毁数据库连接
     *
     * @return bool
     */
    public static function destroy()
    {
        self::$instances = [];
        return true;
    }
}
