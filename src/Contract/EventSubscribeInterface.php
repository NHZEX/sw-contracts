<?php
declare(strict_types=1);

namespace unzxin\zswCore\Contract;

use unzxin\zswCore\Event;

interface EventSubscribeInterface
{
    public function subscribe(Event $event): void;
}
