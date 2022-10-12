<?php
/**
 * TimoPHP a Fast Simple Smart PHP FrameWork
 * Author: Tommy 863758705@qq.com
 * Link: http://www.TimoPHP.com/
 * Since: 2016
 */

namespace Timo\Core;


use Timo\Config\Config;
use Timo\Exception\CoreException;
use Timo\Http\Response;

class Engine
{
    public function __construct()
    {
        $this->init();
    }

    /**
     * 初始化
     */
    public function init()
    {
        spl_autoload_register([__CLASS__, 'loadClass']);

        set_error_handler([__CLASS__, 'errorHandler']);
        set_exception_handler([CoreException::class, 'handle']);

        $this->loadConfig();

        defined('CACHE_PATH') || define('CACHE_PATH', Config::runtime('cache.path'));
    }

    /**
     * 启动应用
     *
     * @throws CoreException
     */
    public function start()
    {
        $router = new Router();
        $controller = $router->getController();
        $action = $router->getAction();
        $params = $router->getParam();

        $this->run($controller, $action, $params);
    }

    /**
     * 运行
     *
     * @param $controller
     * @param $action
     * @param array $params
     * @throws CoreException
     */
    public function run($controller, $action, $params = [])
    {
        App::iniSet($controller, $action, $params);
        $handlerName = $this->getHandlerName($controller);

        $container = App::container();
        $this->loadProvider($container);

        $handler = $container->get($handlerName);
        if (!method_exists($handler, $action)) {
            throw new CoreException('Controller ' . $controller . ' has not method ' . $action, 40001);
        }

        $this->checkMethod($handler, $action);

        $data = call_user_func_array([$handler, $action], $params);
        if ($data != null) {
            Response::default()->send($data);
        }
    }

    /**
     * 加载配置文件
     */
    private function loadConfig()
    {
        $env_config = ROOT_PATH . 'config' . DS . 'env.config.php';
        if ($env_exists = file_exists($env_config)) {
            $env = include $env_config;
            App::$env = $env['env'] ?? 'dev';
            defined('APP_DEBUG') || define('APP_DEBUG', $env['app_debug'] ?? false);
        }
        $env_path = !empty(App::$env) ? App::$env . DS : '';

        $config_files = [
            FRAME_PATH . 'config' . DS . 'config.php',
            dirname(ROOT_PATH) . DS . 'conf' . DS . $env_path . 'db.config.php',
            ROOT_PATH . 'config' . DS . $env_path . 'db.config.php',
            ROOT_PATH . 'config' . DS . $env_path . 'common.config.php'
        ];

        foreach ($config_files as $config_file) {
            if (file_exists($config_file)) {
                Config::load($config_file, 'runtime');
            }
        }
        !$env_exists || Config::load($env, 'runtime');
    }

    /**
     * 获取处理器名称
     *
     * @param $controller
     * @return string
     * @throws CoreException
     */
    private function getHandlerName($controller)
    {
        $name = 'app\\' . APP_NAME . '\\controller\\' . $controller;
        $class_file = ROOT_PATH . str_replace('\\', DIRECTORY_SEPARATOR, $name) . '.php';

        if (!file_exists($class_file)) {
            $pos = strrpos($controller, '\\');
            $controller_file = substr($controller, $pos === false ? 0 : $pos + 1);
            $name = 'app\\' . APP_NAME . '\\controller\\' . strtolower($controller) . '\\' . $controller_file;
            $class_file = ROOT_PATH . str_replace('\\', DIRECTORY_SEPARATOR, $name) . '.php';
            if (!file_exists($class_file)) {
                throw new CoreException('controller ' . $name . ' not found.', 40004);
            }
        }
        return $name;
    }

    /**
     * 加载服务提供者
     *
     * @param Container $container
     */
    private function loadProvider(Container $container)
    {
        if ($providers = Config::runtime('providers')) {
            foreach ($providers as $provider) {
                $provider = $container->get($provider);
                $provider->register();
            }
        }
    }

    /**
     * 自动加载
     *
     * @param $class_name
     * @return bool
     * @throws CoreException
     */
    private function loadClass($class_name)
    {
        $class_name = str_replace('\\', DIRECTORY_SEPARATOR, $class_name);
        $pos = strpos($class_name, DIRECTORY_SEPARATOR);
        $space = substr($class_name, 0, $pos);

        if ($space == 'Timo') {
            $class_name = substr($class_name, $pos + 1);
            $class_file = FRAME_PATH . 'src' . DS . $class_name . '.php';
        } else {
            $class_file = ROOT_PATH . $class_name . '.php';
        }

        if (file_exists($class_file)) {
            require $class_file;
            return true;
        }

        return true;
    }

    /**
     * 请求方法检测
     *
     * @param $app
     * @param $action
     * @return bool
     * @throws CoreException
     */
    private function checkMethod($app, $action)
    {
        try {
            $method = Request::method();
            if ($method == 'OPTIONS') {
                return true;
            }
            $reflector = new \ReflectionClass($app);
            $doc = $reflector->getMethod($action)->getDocComment();
            preg_match('/@method (.*)\b/', $doc, $match);
            if (!empty($match) && $method != $match[1]) {
                Response::default()->code(405)->send(App::result(405, 'Method Not Allowed'));
            }
        } catch (\ReflectionException $e) {
            throw new CoreException("Request Method Not Allowed");
        }
        return true;
    }

    /**
     * 错误处理函数
     *
     * @param $code
     * @param $message
     * @param $file
     * @param $line
     */
    public static function errorHandler($code, $message, $file, $line)
    {
        $e = new CoreException($message, $code, $file, $line);
        CoreException::handle($e);
    }
}
