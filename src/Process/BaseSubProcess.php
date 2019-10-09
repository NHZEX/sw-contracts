<?php
declare(strict_types=1);

namespace unzxin\zswCore\Process;

use Closure;
use Exception;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Swoole\Coroutine;
use Swoole\Coroutine\Socket;
use Swoole\Process;
use Swoole\Timer;
use unzxin\zswCore\ProcessPool;
use function HuangZx\debug_string;

abstract class BaseSubProcess
{
    use IPCMessageTrait;

    protected const UNSERIALIZE_ERROR_PREG = '/unserialize\(\): Error at offset (\d+) of (\d+) bytes/m';

    /**
     * @var bool
     */
    protected $waitCoroutineStop = true;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var bool
     */
    protected $debug = false;

    /**
     * @var int 工人ID
     */
    protected $workerId;

    /**
     * @var ProcessPool
     */
    protected $pool;

    /**
     * @var Process 工人进程
     */
    protected $process;
    /**
     * @var bool
     */
    protected $running = true;
    /**
     * @var int
     */
    protected $checkParentSurviveTime = 0;

    /**
     * @param LoggerInterface $logger
     */
    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    /**
     * @return string
     */
    public function workerName(): string
    {
        return static::class;
    }

    /**
     * @return string
     */
    protected function processName(): string
    {
        return "{$this->workerName()}({$this->process->pid})";
    }

    /**
     * @param ProcessPool $pool
     */
    public function setPool(ProcessPool $pool): void
    {
        $this->pool = $pool;
    }

    /**
     * @param bool $debug
     */
    public function setDebug(bool $debug): void
    {
        $this->debug = $debug;
    }

    /**
     * @param int $workerId
     */
    public function setWorkerId(int $workerId): void
    {
        $this->workerId = $workerId;
    }

    /**
     * @return int
     */
    public function getWorkerId(): int
    {
        return $this->workerId;
    }

    /**
     * 构建主进程
     * @param Process|null $process
     * @return Process
     */
    public function makeProcess(?Process $process = null): ?Process
    {
        $this->initIPCMessage();
        if (null !== $process) {
            if ($this->process instanceof Process) {
                throw new RuntimeException('duplicate declaration instance');
            }
            $this->process = $process;
            $this->entrance($process);
            return null;
        }
        return $this->process = new Process(
            Closure::fromCallable([$this, 'entrance']),
            false,
            0,
            true
        );
    }

    /**
     * @return Process
     */
    public function getProcess(): Process
    {
        return $this->process;
    }

    /**
     * 进程入口
     * @param Process $process
     */
    protected function entrance(Process $process): void
    {
        $this->process = $process;
        $this->process->name("php-cs: {$this->workerId}#{$this->processName()}");
        $this->logger->info("sub process {$this->workerId}#{$this->processName()} run");

        // 响应 SIGINT ctrl+c
        Process::signal(SIGINT, Closure::fromCallable([$this, 'stop']));
        // 响应 SIGTERM
        Process::signal(SIGTERM, Closure::fromCallable([$this, 'stop']));

        $this->checkManagerSurvive();
        $this->listenPipeMessage();

        $this->worker();
    }

    /**
     * 检测主进程存活
     */
    protected function checkManagerSurvive()
    {
        if ($this->checkParentSurviveTime) {
            $mpid = $this->pool->getMasterPid();

            if (false == Process::kill($mpid, 0)) {
                $this->logger->warning("manager process [{$mpid}] exited, {$this->processName()} also quit");
                Process::kill($this->process->pid, SIGTERM);
                Timer::clear($this->checkParentSurviveTime);
            }
        } else {
            // 监控主进程存活
            $this->checkParentSurviveTime = Timer::tick(500, Closure::fromCallable([$this, 'checkManagerSurvive']));
        }
    }

    protected function listenPipeMessage()
    {
        Coroutine::create(function () {
            $unix = $this->pool->getWorkerUnix($this->workerId);
            $socket = new Socket(AF_UNIX, SOCK_DGRAM, 0);
            if (false === $socket->bind($unix)) {
                throw new RuntimeException("bind unix failed: ({$socket->errCode}){$socket->errMsg}");
            }
            while ($this->running) {
                try {
                    if (null === $payload = $this->recvIPCMessage($socket, 2.0)) {
                        continue;
                    }
                } catch (Exception $e) {
                    $this->logger->error("ipc message error: ({$e->getCode()}){$e->getMessage()}");
                    continue;
                }
                [, $from, $data] = $payload;
                try {
                    $data = unserialize($data);
                } catch (Exception $exception) {
                    $message = $exception->getMessage();
                    if ($this->debug && preg_match_all(self::UNSERIALIZE_ERROR_PREG, $message, $matches)) {
                        $message .= sprintf(
                            ' <unserialize error at offset %d of: (%d) -> %s>',
                            $matches[1][0],
                            $matches[2][0],
                            debug_string($data, (int) $matches[2][0], 32)
                        );
                    }
                    $this->logger->error("ipc message unserialize failure: " . $message);
                    continue;
                }
                if ($this->onPipeMessage($data, $this->pool->getWorkerName($from))) {
                    continue;
                }
            }
        });
    }

    /**
     * 管道消息发送
     * @param mixed  $data
     * @param string $pipeName
     */
    protected function sendMessage($data, string $pipeName)
    {

        $unix = $this->pool->getWorkerUnix($this->pool->getWorkerId($pipeName));
        if (!is_readable($unix)) {
            throw new RuntimeException("ipc unix failed: $unix can't read");
        }
        $socket = new Socket(AF_UNIX, SOCK_DGRAM, 0);
        $socket->connect($unix);
        $data   = serialize($data);
        $this->sendSocketMessage($socket, $this->workerId, $data);
    }

    /**
     * 进程停止运行
     * @param int|null $signal
     */
    protected function stop(?int $signal)
    {
        $this->logger->info("child process {$this->processName()} receive signal：{$signal}");
        $this->running = false;

        Coroutine::create(function () use ($signal) {
            $this->logger->debug("child process {$this->processName()} exit...");

            $this->onExit();

            if ($this->waitCoroutineStop) {
                // 等待所有协程退出
                $waitTime = microtime(true) + 3;
                foreach (Coroutine::list() as $cid) {
                    $this->logger->debug("wait coroutine #{$cid} exit...");
                    if ($cid === Coroutine::getCid()) {
                        continue;
                    }
                    // TODO 需要引入协程中断
                    while (Coroutine::exists($cid) && microtime(true) < $waitTime) {
                        Coroutine::sleep(0.1);
                    }
                    if (microtime(true) > $waitTime) {
                        // 协程退出超时
                        if ($bt = Coroutine::getBackTrace($cid, DEBUG_BACKTRACE_IGNORE_ARGS, 1)) {
                            $info             = array_pop($bt);
                            $info['file']     = $info['file'] ?? 'null';
                            $info['line']     = $info['line'] ?? 'null';
                            $info['function'] = $info['function'] ?? 'null';
                            $message          = "{$info['file']}:{$info['line']}#{$info['function']}";
                            $this->logger->warning("coroutine #{$cid} time out: {$message}");
                        } else {
                            $this->logger->warning("coroutine #{$cid} time out: does not exist");
                        }
                    }
                }
            } else {
                Coroutine::sleep(1);
            }

            $this->logger->debug("child process {$this->processName()} exit");
            $this->process->exit();
        });
    }

    /**
     * 自定义实现
     */
    abstract protected function worker();

    /**
     * 收到消息事件
     * @param        $data
     * @param string $form
     * @return bool
     */
    abstract protected function onPipeMessage($data, ?string $form): bool;

    /**
     * 进程退出
     */
    abstract protected function onExit(): void;
}
