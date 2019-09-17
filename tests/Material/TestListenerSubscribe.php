<?php
declare(strict_types=1);

namespace unzxin\zswCore\Tests\Material;

use unzxin\zswCore\Contract\EventSubscribeInterface;
use unzxin\zswCore\Event;

class TestListenerSubscribe extends TestListener implements EventSubscribeInterface
{

    public function subscribe(Event $event): void
    {
    }
}
