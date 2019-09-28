<?php
declare(strict_types=1);

namespace unzxin\zswCore;

use Psr\Log\LoggerInterface;
use Swoole\Coroutine\Socket;
use Swoole\Process;
use Swoole\Server;
use unzxin\zswCore\Process\BaseSubProcess;
use unzxin\zswCore\Process\PoolDrive\BasicPool;
use unzxin\zswCore\Process\PoolDrive\PoolInterface;
use unzxin\zswCore\Process\PoolDrive\SwoolePool;

class ProcessPool
{
    /**
     * @var PoolInterface
     */
    private $pool;

    /**
     * @var BaseSubProcess[]
     */
    private $workers = [];

    /**
     * @var int[]
     */
    private $nameMapping = [];

    /**
     * @var int
     */
    private $workerId = 0;

    /**
     * @var int
     */
    private $masterPid = 0;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public static function makeServer(Server $server)
    {
        return new static(new BasicPool($server));
    }

    public static function makeSwooleProcess()
    {
        return new static(new SwoolePool());
    }

    /**
     * ProcessPool constructor.
     * @param $pool
     */
    protected function __construct(PoolInterface $pool)
    {
        $this->pool = $pool;
        $this->masterPid = getmypid();
    }

    /**
     * @param LoggerInterface $logger
     */
    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    /**
     * @return int
     */
    public function getMasterPid(): int
    {
        return $this->masterPid;
    }

    /**
     * @return PoolInterface
     */
    public function getPool(): PoolInterface
    {
        return $this->pool;
    }

    /**
     * @param BaseSubProcess $worker
     */
    public function add(BaseSubProcess $worker)
    {
        $i = $this->workerId++;
        $worker->setWorkerId($i);
        $worker->setPool($this);
        $worker->setLogger($this->logger);
        $this->workers[$i] = $worker;
        $this->nameMapping[$worker->workerName()] = $i;
    }

    /**
     * 获取工人ID
     * @param string $workerName
     * @return int
     */
    public function getWorkerId(string $workerName): int
    {
        return $this->nameMapping[$workerName] ?? -1;
    }

    /**
     * 获取工人进程实例
     * @param int $workerId
     * @return Process
     */
    public function getWorkerProcess(int $workerId): ?Process
    {
        return $this->pool->getWorkerProcess($workerId);
    }

    /**
     * 获取工人命名
     * @param int $workerId
     * @return string
     */
    public function getWorkerName(int $workerId): ?string
    {
        if ($worker = ($this->workers[$workerId] ?? null)) {
            return $worker->workerName();
        } else {
            return null;
        }
    }

    /**
     * 获取 IPC Socket
     * @param string $workerName
     * @return Socket
     */
    public function getWorkerSocket(string $workerName): ?Socket
    {
        if ($process = $this->getWorkerProcess($this->getWorkerId($workerName))) {
            return $process->exportSocket();
        } else {
            return null;
        }
    }

    public function start()
    {
        $this->pool->setWorkers($this->workers);
        $this->pool->setLogger($this->logger);
        $this->pool->start();
    }
}
