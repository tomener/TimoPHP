<?php
/**
 * TimoPHP a Fast Simple Smart PHP FrameWork
 * Author: Tommy 863758705@qq.com
 * Link: http://www.TimoPHP.com/
 * Since: 2016
 */

namespace Timo\Orm;


use PDO;

/**
 * @method Query table($name)
 * @method Query select($select)
 * @method Query alias($alias)
 * @method Query join($join)
 * @method Query params($params)
 * @method Query groupBy($groupBy)
 * @method Query orderBy($orderBy)
 * @method Query having($having)
 * @method Query limit($limit)
 * @method Query mode($mode)
 * @method Query sql($mode)
 * @method array row(string $select = '')
 * @method array list($need_page = true)
 * @method array column(string $select, $key = null)
 * @method int|string|false|null value(string $field = '')
 * @method bool|int inc(string $field, int $step = 1)
 * @method bool|int dec(string $field, int $step = 1)
 * @method int count(string $field = '*')
 * @method int|mixed sum(string $field = '*')
 * @method int|mixed avg(string $field)
 * @method int|mixed max(string $field)
 * @method int|mixed min(string $field)
 * @method bool|string insert(array $data)
 * @method bool|string insertList($data, $returnId = false)
 * @method bool|int update(array $data)
 * @method bool|int delete(array $data)
 */
class Query
{
    protected $connection;

    public $qos = [
        'table' => '',
        'alias' => '',
        'select' => '*',
        'join' => '',
        'where' => [],
        'forUpdate' => false,
        'params' => [],
        'groupBy' => '',
        'orderBy' => '',
        'having' => '',
        'limit' => '',
        'page' => [],
        'mode' => PDO::FETCH_ASSOC,
        'condition' => '',
        'sql' => '',
    ];

    public function connection()
    {
        return $this->connection;
    }

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * @param $name
     * @param $arguments
     * @return Query
     */
    public function __call($name, $arguments)
    {
        if (isset($this->qos[$name])) {
            $this->qos[$name] = $arguments[0];
            return $this;
        }

        array_unshift($arguments, $this);
        return call_user_func_array([$this->connection, $name], $arguments);
    }

    /**
     * 设置条件
     *
     * @param $column
     * @param null $operator
     * @param null $value
     * @return $this
     */
    public function where($column, $operator = null, $value = null)
    {
        if ($column instanceof \Closure) {
            if (empty($column())) {
                return $this;
            }
            $this->qos['where'][] = ' AND (';
            $this->qos['where'][] = $column;
            $this->qos['where'][] = ')';
        } else {
            if (!is_array($column) && is_null($operator) && is_null($value)) {
                $operator = '=';
                $value = $column;
                $column = $this->connection->getPrimaryKey($this->qos['table']);
            }
            $this->qos['where'] = array_merge($this->qos['where'], $this->connection->parseWhere($column, $operator, $value));
        }

        return $this;
    }

    /**
     * 设置或条件
     *
     * @param $column
     * @param null $operator
     * @param null $value
     * @return $this
     */
    public function orWhere($column, $operator = null, $value = null)
    {
        if ($column instanceof \Closure) {
            if (empty($column())) {
                return $this;
            }
            $this->qos['where'][] = !empty($this->qos['where']) ? ' OR (' : ' (';
            $this->qos['where'][] = $column;
            $this->qos['where'][] = ')';
        } else {
            if (!is_array($column) && is_null($operator) && is_null($value)) {
                $operator = '=';
                $value = $column;
                $column = $this->connection->getPrimaryKey($this->qos['table']);
            }
            $this->qos['where'] = array_merge($this->qos['where'], $this->connection->parseWhere($column, $operator, $value, 'OR'));
        }
        return $this;
    }

    /**
     * 分页设置
     *
     * @param $page
     * @return $this
     */
    public function page(&$page)
    {
        $this->qos['page'] = &$page;
        $this->qos['limit'] = ($page['p'] - 1) * $page['limit'] . ',' . $page['limit'];
        return $this;
    }

    /**
     * 排它锁（X锁）
     *
     * 排它锁：加锁之前请开启事务，加锁之后，其它事务只能读取不能更新，如果其它事务也加了for update，
     * 那其它事务会阻塞等待前一个加锁的事务提交之后才能读取并加锁
     *
     * @return $this
     */
    public function forUpdate()
    {
        $this->qos['forUpdate'] = true;
        return $this;
    }
}
