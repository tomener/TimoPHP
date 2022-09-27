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

    protected $map = [
        'serve' => [
            '-h' => 'host',
            '-p' => 'port',
            '-r' => 'root'
        ],
    ];

    private $commands = [
        '-v' => 'version',
        '-version' => 'version',
        '-h' => 'help',
    ];

    public function __construct()
    {
        $this->engine = new Engine();
        $this->engine->init();

        $this->parse();
    }

    public function start()
    {
        if (method_exists($this, $this->command)) {
            call_user_func_array([$this, $this->command], []);
            return;
        }

        $seg = explode(":", $this->command);
        if (count($seg) != 2) {
            die("The command {$this->command} is not supported\n");
        }

        $this->parseRoute($seg);
    }

    /**
     * 框架版本号
     */
    public function version()
    {
        echo self::colorLightBlue('TimoPHP v' . VERSION) . PHP_EOL;
    }

    /**
     * 帮助文档
     */
    public function help()
    {
        echo self::colorLightBlue('Usage') . ":\n\n"
            . " " . self::colorLightRed('-h') . " help\n"
            . " " . self::colorLightRed('-v -version') . " version\n";
    }

    /**
     * php timo serve -h 127.0.0.1 -p 8080 -r
     */
    public function serve()
    {
        $host = $this->params['host'] ?? '0.0.0.0';
        $port = $this->params['port'] ?? '8090';
        $root = $this->params['root'] ?? '';
        if (empty($root)) {
            $root = ROOT_PATH . 'public';
        }

        $command = sprintf(
            'php -S %s:%d -t %s %s',
            $host,
            $port,
            escapeshellarg($root),
            escapeshellarg($root . DIRECTORY_SEPARATOR . 'router.php')
        );

        printf(self::colorGreen('TimoPHP Development server is started On') . " <http://%s:%s/>\n", $host, $port);
        printf("You can exit with `".self::colorLightRed("CTRL-C")."`\n");
        printf("Document root is: %s\n", $root);
        passthru($command);
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

        $controller = ucfirst($controller);
        $this->engine->run($controller, $action, $this->params);
    }

    /**
     * 命令行解析
     */
    private function parse()
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

        $inputs = array_slice($argv, 2);
        $this->parseParams($inputs);
    }

    /**
     * 解析参数
     *
     * @param $inputs
     */
    private function parseParams($inputs)
    {
        $map = isset($this->map[$this->command]) ? $this->map[$this->command] : [];

        $key = '';
        foreach ($inputs as $input) {
            if (isset($map[$input])) {
                $key = $map[$input];
            } elseif (substr($input, 0, 1) == '-') {
                $key = substr($input, 1);
            } elseif (!empty($key)) {
                $this->params[$key] = $input;
                $key = '';
            }
        }
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
