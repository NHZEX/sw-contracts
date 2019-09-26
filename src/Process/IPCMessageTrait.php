<?php
declare(strict_types=1);

namespace unzxin\zswCore\Process;

use RuntimeException;
use Swoole\Coroutine\Socket;

trait IPCMessageTrait
{
    /**
     * @var IPCMessageProtocol
     */
    protected $ipcBuffer;

    /**
     * 初始化IPC通信
     */
    protected function initIPCMessage()
    {
        $this->ipcBuffer = new IPCMessageProtocol();
    }

    /**
     * 接受进程IPC通信
     * @param Socket $socket
     * @param float  $timeout
     * @return array|null
     */
    protected function recvIPCMessage(Socket $socket, float $timeout = 2.0): ?array
    {
        if (!$recv = $socket->recv($this->ipcBuffer->getChunkSize(), $timeout)) {
            if (0 !== $socket->errCode && $socket->errCode !== SOCKET_ETIMEDOUT) {
                throw new RuntimeException("[ipc] socket recv error: ({$socket->errCode}){{$socket->errMsg}}");
            }
            return null;
        }
        // 从解析器中获取数据帧
        return  $this->ipcBuffer->read($recv);
    }

    /**
     * 发送进程IPC信息
     * @param Socket $socket
     * @param int    $workerId
     * @param mixed  $data
     */
    protected function sendIPCMessage(Socket $socket, int $workerId, string $data): void
    {
        foreach ($this->ipcBuffer->generateMsgChunk($workerId, $data) as $chunk) {
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
