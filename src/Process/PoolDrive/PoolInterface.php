<?php
declare(strict_types=1);

namespace unzxin\zswCore\Process\PoolDrive;

use Psr\Log\LoggerInterface;
use Swoole\Process;
use unzxin\zswCore\Process\BaseSubProcess;

interface PoolInterface
{
    /**
     * @param LoggerInterface $logger
     */
    public function setLogger(LoggerInterface $logger): void;

    /**
     * @param BaseSubProcess[] $workers
     * @return void
     */
    public function setWorkers(array $workers): void;

    public function onPoolStart(callable $call): void;

    /**
     * @param int $workerId
     * @return Process|null
     */
    public function getWorkerProcess(int $workerId): ?Process;

    public function start(): void;
}
