<?php
/**
 * TimoPHP a Fast Simple Smart PHP FrameWork
 * Author: Tommy 863758705@qq.com
 * Link: http://www.TimoPHP.com/
 * Since: 2016
 */

namespace Timo\Console;


use Timo\Core\Engine;
use Timo\Core\Router;
use Timo\Exception\CoreException;

class Console
{
    protected $engine;

    protected $command = null;

    protected $params = [];

    private $commands = [
        '-v' => 'version',
        '-version' => 'version',
        '-c' => 'create',
        '-h' => 'help',
    ];

    public function __construct()
    {
        $this->engine = new Engine();
        $this->engine->init();
    }

    public function start()
    {
        $this->parseParams();
        if (method_exists($this, $this->command)) {
            call_user_func_array(array($this, $this->command), $this->params);
            return;
        }

        $seg = explode(":", $this->command);
        if (count($seg) != 2) {
            die("The command {$this->command} is not supported\n");
        }

        $this->parseRoute($seg);
    }

    /**
     * CLI模式解析路由
     *
     * @throws CoreException
     */
    private function parseRoute($router)
    {
        Router::loadAppConfig();

        $controller = $router[0];
        $action = $router[1];
        $params = [];

        if (count($this->params) > 0) {
            foreach ($this->params as $param) {
                $temp = explode('=', $param);
                $params[$temp[0]] = $temp[1];
            }
        }

        $controller = ucfirst($controller);
        $this->engine->run($controller, $action, $params);
    }

    /**
     * 框架版本号
     */
    public function version()
    {
        echo self::colorLightBlue('TimoPHP v' . VERSION) . PHP_EOL;
    }

    private function parseParams()
    {
        global $argv;
        global $argc;
        if ($argc < 2) {
            die("Lack of command parameters\n create project_name\n");
        }

        $this->command = $argv[1];
        if (isset($this->commands[$argv[1]])) {
            $this->command = $this->commands[$argv[1]];
        }

        $this->params = array_slice($argv, 2);
    }

    public static function colorLightRed($str)
    {
        return "\033[1;31m" . $str . "\033[0m";
    }

    public static function colorLightBlue($str)
    {
        return "\033[1;34m" . $str . "\033[0m";
    }

    public static function colorGreen($str)
    {
        return "\033[0;32m" . $str . "\033[0m";
    }
}
