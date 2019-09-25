<?php
declare(strict_types=1);

namespace unzxin\zswCore\Process;

use App\Process\IPCMessageProtocol;
use RuntimeException;
use Swoole\Coroutine;
use Swoole\Coroutine\Socket;

trait IPCMessageTrait
{
    /**
     * @var IPCMessageProtocol
     */
    protected $pipeBuffer;

    /**
     * 监听进程IPC通信
     * @param Socket   $socket
     * @param callable $handle
     * @param bool     $running
     * @param float    $timeout
     */
    protected function listenIPCMessage(Socket $socket, callable $handle, bool &$running, float $timeout = 2.0)
    {
        Coroutine::create(function () use ($socket, $handle, &$running, $timeout) {
            while ($running) {
                if (false === $recv = $socket->recv(IPCMessageProtocol::CHUNK_SIZE, $timeout)) {
                    if ($socket->errCode !== SOCKET_ETIMEDOUT) {
                        throw new RuntimeException("[ipc] socket recv error: ({$socket->errCode}){{$socket->errMsg}}");
                    }
                    continue;
                }
                // 从解析器中获取数据帧
                while ($payload = $this->pipeBuffer->read($recv)) {
                    call_user_func($handle, $payload);
                    break;
                }
            }
        });
    }

    /**
     * 发送进程IPC信息
     * @param Socket $socket
     * @param int    $workerId
     * @param mixed  $data
     */
    protected function sendIPCMessage(Socket $socket, int $workerId, $data)
    {
        $data = serialize($data);
        foreach ($this->pipeBuffer->generateMsgChunk($workerId, $data) as $chunk) {
            $send_len = $socket->sendAll($chunk);
            if (false === $send_len) {
                throw new RuntimeException("data transmission failed: ({$socket->errCode}){$socket->errMsg}");
            }
            $len = strlen($chunk);
            if ($send_len > $len) {
                throw new RuntimeException("wrong data chunk transmission length {$len} !== {$send_len}");
            }
        }
    }
}
