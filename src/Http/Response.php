<?php
/**
 * TimoPHP a Fast Simple Smart PHP FrameWork
 * Author: Tommy 863758705@qq.com
 * Link: http://www.TimoPHP.com/
 * Since: 2016
 */

namespace Timo\Http;


use Timo\Config\Config;

class Response
{
    private static $response;

    protected $contentType = [
        'json'   => 'application/json',
        'xml'    => 'text/xml',
        'html'   => 'text/html',
        'jsonp'  => 'application/javascript',
        'script' => 'application/javascript',
        'text'   => 'text/plain',
    ];

    private $type = '';

    private $headers = [
        'Content-Type' => 'text/html; charset=utf-8'
    ];

    private $code = 200;

    protected $option = [
        'is_return' => false,
        'is_exit' => true,
    ];

    /**
     * 创建响应
     *
     * @return Response
     */
    public static function instance()
    {
        if (is_null(self::$response)) {
            self::$response = new Response();
        }

        return self::$response;
    }

    /**
     * 创建响应对象并设置响应类型
     *
     * @param $type string html json
     */
    public static function new($type = '')
    {
        $response = new Response();
        if (!empty($type)) {
            $response->type = $type;
        }
        $response->headers['Content-Type'] = $response->contentType[$response->type] . '; charset=utf-8';
        return $response;
    }

    /**
     * 默认响应
     *
     * @return Response
     */
    public static function default()
    {
        $response = self::instance();
        $response->type = Config::runtime('http_content_type');
        $response->headers['Content-Type'] = $response->contentType[$response->type] . '; charset=utf-8';
        return $response;
    }

    /**
     * 发送数据
     *
     * @param $data
     * @return false|string|null
     */
    public function send($data)
    {
        if (!headers_sent()) {
            $this->sendHeaders();
        }

        switch ($this->type) {
            case 'json':
                $data = json_encode($data, JSON_UNESCAPED_UNICODE);
                break;
            case 'jsonp':
                $handler = !empty($_GET[Config::runtime('var_jsonp_handler')]) ? $_GET[Config::runtime('var_jsonp_handler')] : Config::runtime('default_jsonp_handler');
                $data    = $handler . '(' . json_encode($data, JSON_UNESCAPED_UNICODE) . ');';
                break;
            case '':
            case 'html':
            case 'text':
                // 不做处理
                break;
        }

        if ($this->option['is_return']) {
            return $data;
        }

        //发送数据
        echo $data;
        if ($this->option['is_exit']) {
            exit;
        }
        return null;
    }

    /**
     * 设置响应头
     *
     * @param $name
     * @param null $value
     * @return $this
     */
    public function header($name, $value = null)
    {
        if (!is_array($name)) {
            $this->headers[$name] = $value;
        } else {
            $this->headers = array_merge($this->headers, $name);
        }
        return $this;
    }

    /**
     * 发送响应头
     */
    private function sendHeaders()
    {
        http_response_code($this->code);
        foreach ($this->headers as $key => $value) {
            header($key . (!is_null($value) ? ': ' . $value : ''));
        }
    }

    /**
     * 获取或设置响应数据类型
     *
     * @param $type
     * @return $this
     */
    public function type($type = null)
    {
        if ($type != null) {
            $this->type = $type;
            $this->headers['Content-Type'] = $this->contentType[$type] . '; charset=utf-8';
            return $this;
        }

        if ($this->type == '') {
            $this->type = Config::runtime('http_content_type');
        }
        return $this->type;
    }

    /**
     * 响应状态码
     *
     * @param $code int 200
     * @return Response
     */
    public function code($code)
    {
        $this->code = $code;
        return $this;
    }

    /**
     * 重定向
     *
     * @param string $url
     * @param int $code
     */
    public static function redirect($url, $code = 302)
    {
        http_response_code($code);
        header('Location:' . $url);
        exit;
    }

    /**
     * 是否返回数据
     *
     * @param bool $is_return
     * @return $this
     */
    public function isReturn(bool $is_return)
    {
        $this->option['is_return'] = $is_return;
        return $this;
    }

    /**
     * 发送数据后是否退出
     *
     * @param bool $is_exit
     * @return $this
     */
    public function isExit(bool $is_exit)
    {
        $this->option['is_exit'] = $is_exit;
        return $this;
    }
}
