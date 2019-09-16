<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2019 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------
declare(strict_types=1);

namespace HZEX\TpSwoole;

use Closure;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use RuntimeException;
use think\Container;

/**
 * 事件管理类
 * @method Event onSwooleStart(callable $fun); // Master
 * @method Event onSwooleShutdown(callable $fun); // Master
 * @method Event onSwooleManagerStart(callable $fun); // Manager
 * @method Event onSwooleManagerStop(callable $fun); // Manager
 * @method Event onSwooleWorkerStart(callable $fun); // Worker
 * @method Event onSwooleWorkerStop(callable $fun); // Worker
 * @method Event onSwooleWorkerExit(callable $fun); // Worker
 * @method Event onSwooleWorkerError(callable $fun); // Worker
 * @method Event onSwoolePipeMessage(callable $fun); // Message
 * @method Event onSwooleTask(callable $fun); // Task
 * @method Event onSwooleFinish(callable $fun); // Task
 * @method Event onSwooleConnect(callable $fun); // Tcp
 * @method Event onSwooleReceive(callable $fun); // Tcp
 * @method Event onSwooleClose(callable $fun); // Tcp
 * @method Event onSwoolePacket(callable $fun); // Udp
 * @method Event onSwooleRequest(callable $fun); // Http Server
 * @method Event onSwooleHandShake(callable $fun); // WebSocket Server
 * @method Event onSwooleOpen(callable $fun); // WebSocket Server
 * @method Event onSwooleMessage(callable $fun); // WebSocket Server
 * @method mixed trigSwooleStart(...$params); // Master
 * @method mixed trigSwooleShutdown(...$params); // Master
 * @method mixed trigSwooleManagerStart(...$params); // Manager
 * @method mixed trigSwooleManagerStop(...$params); // Manager
 * @method mixed trigSwooleWorkerStart(...$params); // Worker
 * @method mixed trigSwooleWorkerStop(...$params); // Worker
 * @method mixed trigSwooleWorkerExit(...$params); // Worker
 * @method mixed trigSwooleWorkerError(...$params); // Worker
 * @method mixed trigSwoolePipeMessage(...$params); // Message
 * @method mixed trigSwooleTask(...$params); // Task
 * @method mixed trigSwooleFinish(...$params); // Task
 * @method mixed trigSwooleConnect(...$params); // Tcp
 * @method mixed trigSwooleReceive(...$params); // Tcp
 * @method mixed trigSwooleClose(...$params); // Tcp
 * @method mixed trigSwoolePacket(...$params); // Udp
 * @method mixed trigSwooleRequest(...$params); // Http Server
 * @method mixed trigSwooleHandShake(...$params); // WebSocket Server
 * @method mixed trigSwooleOpen(...$params); // WebSocket Server
 * @method mixed trigSwooleMessage(...$params); // WebSocket Server
 */
class Event
{
    /**
     * 监听者
     * @var array
     */
    protected $listener = [];

    /**
     * 事件别名
     * @var array
     */
    protected $bind = [];

    /**
     * 是否需要事件响应
     * @var bool
     */
    protected $withEvent = true;

    /**
     * 应用对象
     * @var ContainerInterface
     */
    protected $app;

    public function __construct(Container $app)
    {
        $this->app = $app;
    }

    /**
     * 设置是否开启事件响应
     * @access protected
     * @param bool $event 是否需要事件响应
     * @return $this
     */
    public function withEvent(bool $event)
    {
        $this->withEvent = $event;
        return $this;
    }

    /**
     * 批量注册事件监听
     * @access public
     * @param array $events 事件定义
     * @return $this
     */
    public function listenEvents(array $events)
    {
        if (!$this->withEvent) {
            return $this;
        }

        foreach ($events as $event => $listeners) {
            if (isset($this->bind[$event])) {
                $event = $this->bind[$event];
            }

            $this->listener[$event] = array_merge($this->listener[$event] ?? [], $listeners);
        }

        return $this;
    }

    /**
     * 注册事件监听
     * @access public
     * @param string   $event    事件名称
     * @param callable $listener 监听操作（或者类名）
     * @param bool     $first    是否优先执行
     * @return $this
     */
    public function listen(string $event, callable $listener, bool $first = false)
    {
        if (!$this->withEvent) {
            return $this;
        }

        if (isset($this->bind[$event])) {
            $event = $this->bind[$event];
        }

        if ($first && isset($this->listener[$event])) {
            array_unshift($this->listener[$event], $listener);
        } else {
            $this->listener[$event][] = $listener;
        }

        return $this;
    }

    /**
     * 是否存在事件监听
     * @access public
     * @param string $event 事件名称
     * @return bool
     */
    public function hasListener(string $event): bool
    {
        if (isset($this->bind[$event])) {
            $event = $this->bind[$event];
        }

        return isset($this->listener[$event]);
    }

    /**
     * 移除事件监听
     * @access public
     * @param string $event 事件名称
     * @return void
     */
    public function remove(string $event): void
    {
        if (isset($this->bind[$event])) {
            $event = $this->bind[$event];
        }

        unset($this->listener[$event]);
    }

    /**
     * 指定事件别名标识 便于调用
     * @access public
     * @param array $events 事件别名
     * @return $this
     */
    public function bind(array $events)
    {
        $this->bind = array_merge($this->bind, $events);

        return $this;
    }

    /**
     * 注册事件订阅者
     * @access public
     * @param object|object[] $subscriber 订阅者
     * @param array           $events     事件列表
     * @return $this
     */
    public function subscribe($subscriber, array $events = [])
    {
        if (!$this->withEvent) {
            return $this;
        }

        $subscribers = is_array($subscriber) ? $subscriber : [$subscriber];

        foreach ($subscribers as $subscriber) {
            if ($subscriber instanceof EventSubscribeInterface) {
                // 手动订阅
                $subscriber->subscribe($this);
            } else {
                // 智能订阅
                $this->observe($subscriber, $events);
            }
        }

        return $this;
    }

    /**
     * 自动注册事件观察者
     * @access public
     * @param object $observer 观察者
     * @param array  $events   事件列表
     * @return $this
     */
    public function observe($observer, array $events = [])
    {
        if (!$this->withEvent) {
            return $this;
        }

        if (!empty($events)) {
            foreach ($events as $event) {
                $name   = false !== strpos($event, '\\') ? substr(strrchr($event, '\\'), 1) : $event;
                $method = 'on' . $name;

                if (method_exists($observer, $method)) {
                    $this->listen($event, Closure::fromCallable([$observer, $method]));
                }
            }
        } else {
            try {
                $reflect = new ReflectionClass($observer);
            } catch (ReflectionException $e) {
                throw new RuntimeException('observe class invalid');
            }
            $methods = $reflect->getMethods(ReflectionMethod::IS_PUBLIC);

            foreach ($methods as $method) {
                $name = $method->getName();
                if (0 === strpos($name, 'on')) {
                    $this->listen(substr($name, 2), Closure::fromCallable([$observer, $name]));
                }
            }
        }

        return $this;
    }

    /**
     * 触发事件
     * @access public
     * @param string|object $event  事件名称
     * @param mixed         $params 传入参数
     * @param bool          $once   只获取一个有效返回值
     * @return mixed
     */
    public function trigger($event, $params = null, bool $once = false)
    {
        if (!$this->withEvent) {
            return null;
        }

        if (is_object($event)) {
            $params = $event;
            $event  = get_class($event);
        }

        if (isset($this->bind[$event])) {
            $event = $this->bind[$event];
        }

        $result    = [];
        $listeners = $this->listener[$event] ?? [];
        $params    = is_array($params) ? $params : [$params];

        foreach ($listeners as $key => $listener) {
            $result[$key] = $this->dispatch($listener, ...$params);

            if (false === $result[$key] || (!is_null($result[$key]) && $once)) {
                break;
            }
        }

        return $once ? end($result) : $result;
    }

    /**
     * 触发事件(只获取一个有效返回值)
     * @param      $event
     * @param null $params
     * @return mixed
     */
    public function until($event, $params = null)
    {
        return $this->trigger($event, $params, true);
    }

    /**
     * 执行事件调度
     * @access protected
     * @param mixed $event  事件方法
     * @param mixed $params 参数
     * @return mixed
     */
    protected function dispatch($event, ...$params)
    {
        if (!is_string($event)) {
            $call = $event;
        } elseif (strpos($event, '::')) {
            $call = $event;
        } else {
            $obj  = $this->app->make($event);
            $call = [$obj, 'handle'];
        }

        return $this->app->invoke($call, $params);
    }

    /**
     * 触发事件
     * @access public
     * @param string|object $event  事件名称
     * @param mixed         $params 传入参数
     * @param bool          $once   只获取一个有效返回值
     * @return mixed
     */
    public function triggerSwoole($event, $params = null, bool $once = false)
    {
        return $this->trigger('sw.' . strtolower($event), $params, $once);
    }

    /**
     * @param $name
     * @param $arguments
     * @return $this|mixed
     */
    public function __call(string $name, $arguments)
    {
        if (0 === strpos($name, 'trigSwoole')) {
            $name = strtolower(substr($name, 10));
            return $this->trigger('sw.' . $name, $arguments[0]);
        } elseif (0 === strpos($name, 'onSwoole')) {
            $name = strtolower(substr($name, 8));
            return $this->listen('sw.' . $name, $arguments[0]);
        }

        throw new RuntimeException('Unknown method ' . $name);
    }
}
