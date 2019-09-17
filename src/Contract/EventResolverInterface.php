<?php
declare(strict_types=1);

namespace unzxin\zswCore\Contract;

use unzxin\zswCore\Event;

interface EventResolverInterface
{
    /**
     * 构建类
     * @param       $classNamse
     * @param Event $event
     * @return mixed
     */
    public function makeClass($classNamse, Event $event);

    /**
     * 调用方法
     * @param       $callable
     * @param array $vars
     * @param Event $event
     * @return mixed
     */
    public function invoke($callable, array $vars, Event $event);
}
