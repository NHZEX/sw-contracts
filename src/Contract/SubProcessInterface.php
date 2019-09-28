<?php
declare(strict_types=1);

namespace HZEX\TpSwoole\Contract;

use Swoole\Process;

interface SubProcessInterface
{
    /**
     * @return Process
     */
    public function makeProcess(): Process;
}
