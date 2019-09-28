<?php
declare(strict_types=1);

namespace unzxin\zswCore\Process\PoolDrive;

use Closure;
use Psr\Log\LoggerInterface;
use Swoole\Coroutine;
use Swoole\Process;
use Swoole\Server;
use unzxin\zswCore\Process\BaseSubProcess;

class BasicPool implements PoolInterface
{
    /**
     * @var BaseSubProcess[]
     */
    private $workers = [];

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var Server
     */
    private $server;

    /**
     * @var callable
     */
    private $onPoolStart;

    /**
     * BasicPool constructor.
     * @param Server $server
     */
    public function __construct(Server $server)
    {
        $this->server = $server;
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
        $worker = $this->workers[$workerId] ?? null;
        if ($worker) {
            return $worker->getProcess();
        }
        return null;
    }

    public function start(): void
    {
        Coroutine::create(Closure::fromCallable([$this, 'onStart']));
        foreach ($this->workers as $worker) {
            $this->server->addProcess($worker->makeProcess());
        }
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
}
