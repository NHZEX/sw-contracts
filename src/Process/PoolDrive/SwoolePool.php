<?php
declare(strict_types=1);

namespace unzxin\zswCore\Process\PoolDrive;

use Closure;
use Psr\Log\LoggerInterface;
use Swoole\Process;
use Swoole\Process\Pool;
use unzxin\zswCore\Process\BaseSubProcess;

class SwoolePool implements PoolInterface
{
    /**
     * @var BaseSubProcess[]
     */
    private $workers = [];

    /**
     * @var Pool 进程池
     */
    private $swPool;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var callable
     */
    private $onPoolStart;

    /**
     * SwoolePool constructor.
     */
    public function __construct()
    {
    }

    /**
     * @param LoggerInterface $logger
     */
    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    /**
     * @param BaseSubProcess[] $workers
     * @return void
     */
    public function setWorkers(array $workers): void
    {
        $this->workers = $workers;
    }

    public function onPoolStart(callable $call): void
    {
        $this->onPoolStart = $call;
    }

    /**
     * @param int $workerId
     * @return Process|null
     */
    public function getWorkerProcess(int $workerId): ?Process
    {
        $process = $this->swPool->getProcess($workerId);
        if ($process instanceof Process) {
            return $process;
        }
        return null;
    }

    public function start(): void
    {
        $this->swPool = new Pool(count($this->workers), 0, 0, true);
        $this->swPool->on('WorkerStart', Closure::fromCallable([$this, 'onWorkerStart']));
        $this->swPool->on('WorkerStop', Closure::fromCallable([$this, 'onWorkerStop']));
        $this->swPool->on('Start', Closure::fromCallable([$this, 'onStart']));
        $this->swPool->start();
    }

    /**
     * 进程池启动
     */
    protected function onStart()
    {
        if (is_callable($this->onPoolStart)) {
            call_user_func($this->onPoolStart, $this);
        }
    }

    /**
     * 工人启动
     * @param Pool $pool
     * @param      $workerId
     */
    protected function onWorkerStart(Pool $pool, $workerId)
    {
        foreach ($this->workers as $worker) {
            if ($workerId === $worker->getWorkerId()) {
                $worker->makeProcess($pool->getProcess());
            }
        }
    }

    /**
     * 工人停止 [无法调用!]
     * @param Pool $pool
     * @param      $workerId
     */
    protected function onWorkerStop(Pool $pool, $workerId)
    {
    }
}
