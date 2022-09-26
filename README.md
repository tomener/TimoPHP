## TimoPHP

一个简单、快速、规范、灵活、扩展性好的PHP MVC+框架，主要用于API接口开发（前后端分离已是常态）

官网：http://timo.gumaor.com/

文档：http://timo.gumaor.com/document/

## 我们的目标

做一个轻量级（能简单就不要复杂）并支持大型应用开发的PHP框架

## MVC+模式

除了M层，我们还可以根据项目实际情况增加层，比如，业务逻辑层（business/logic）服务层（Service）策略层（strategy）等等

## 特点
    1、PHP7.0+
    2、PSR-4标准自动加载
    3、轻量级，扩展灵活
    4、支持单应用、多应用、多版本API
    5、多环境支持，如开发环境（dev）测试环境（test）线上环境（pro）
    6、原生模版解析
    7、模板支持多主题、layout（布局）
    8、加入对cli模式支持，用来写服务、定时脚本挺好的
    9、增加依赖注入服务容器，实现组件之间的松耦合
    10、ORM链式调用，支持数据库读写分离设置，可具体到某张表
    
## 目录结构

```
/data
  |-hoole                           项目目录(自己项目名称)
  |   |-app                         应用目录
  |   |   |-admin                   后台接口应用
  |   |   |-api                     前台接口应用
  |   |   |   |-controller          控制器目录
  |   |   |   |_config.php          项目配置文件（可以去除）
  |   |   |-cli                     命令行应用
  |   |-business                    公共业务逻辑
  |   |-cache                       运行时缓存目录
  |   |-provider                    服务提供者目录
  |   |-config                      公共配置目录
  |   |   |-dev                     开发环境配置目录
  |   |   |-pro                     线上环境配置目录
  |   |   |-test                    测试环境配置目录
  |   |   |-env.config.php          环境配置文件（主要放置一些比较敏感的配置，不要提交到git）
  |   |-lib                         自定义类库
  |   |-logs                        日志目录
  |   |-model                       模型目录
  |   |-public                      WEB目录（对外访问目录）名称自定义
  |   |   |-admin                   后台入口
  |   |   |-api                     前台入口
  |   |   |   |_index.php           前台入口文件
  |   |-send                        推送（微信、小程序、android、IOS）
  |   |-service                     服务层
  |   |-task                        异步任务
  |   |-vendor                      composer安装类库目录
  |   |_composer.json
  |-TimoPHP                         框架，和项目在同一级目录
 ```

## 新建一个项目
```
php TimoPHP/bin/timo -c 项目名称 应用名称 [应用类型]
应用类型：api或者web，默认api（接口类型）

#示例，在当前目录创建一个项目hoole，并在项目下创建了一个应用api
php TimoPHP/bin/timo -c hoole api
```


## 入口模式

##### 多入口
一个应用一个入口，默认

##### 单一入口
所有应用共用一个入口
