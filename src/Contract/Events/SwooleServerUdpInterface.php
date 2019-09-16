<?php
declare(strict_types=1);

namespace unzxin\zswCore\Contract\Event;

use Swoole\Server;

interface SwooleServerUdpInterface extends SwooleEventInterface
{
    /**
     * 数据包到达回调（Udp）
     * @param Server $server
     * @param string $data
     * @param array  $clientInfo
     */
    public function onPacket(Server $server, string $data, array $clientInfo): void;
}
