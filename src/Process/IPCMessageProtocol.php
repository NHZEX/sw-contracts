<?php
declare(strict_types=1);

namespace unzxin\zswCore\Process;

use Generator;
use RuntimeException;

/**
 * Class IPCMessage
 * @package App\IPCMessage
 * TODO 需要预防内存泄漏问题
 */
class IPCMessageProtocol
{
    /**
     * 封装协议: IPC
     */
    public const PROTOCOL_IPC = 1;

    /**
     * 通信报文头长度
     */
    private const HEAD_LEN = 10;

    /**
     * 通信报文块尺寸
     */
    protected $chunkSize = 65535;

    /**
     * 消息Id
     * @var int
     */
    protected $ipcMessageId = 0;

    /**
     * 消息缓冲集合
     * @var string[]
     */
    private $bufferSet = [];

    /**
     * @param int $chunkSize
     */
    public function setChunkSize(int $chunkSize): void
    {
        $this->chunkSize = $chunkSize;
    }

    /**
     * @return int
     */
    public function getChunkSize(): int
    {
        return $this->chunkSize;
    }

    /**
     * 分块传输数据
     * @param int    $workerId
     * @param string $payload
     * @return Generator
     */
    public function generateMsgChunk(int $workerId, string $payload): Generator
    {
        $messageId = ++$this->ipcMessageId;
        $payload_len = strlen($payload);
        $real_chunk_size = $this->chunkSize - self::HEAD_LEN;
        $chunk_id = 0;
        $send_size = 0;
        do {
            $send_payload = substr($payload, $chunk_id * $real_chunk_size, $real_chunk_size);
            $send_size += strlen($send_payload);
            $chunk_id++;
            if ($send_size > $payload_len) {
                throw new RuntimeException("wrong data transmission length {$send_size} > {$payload_len}");
            }
            $data = pack('CNNC', self::PROTOCOL_IPC, $workerId, $messageId, $send_size === $payload_len);
            yield $data . $send_payload;
        } while ($send_size !== $payload_len);
    }

    /**
     * 尝试从数据块中合并数据
     * @param string $data
     * @return string|array|null
     */
    public function read(string $data)
    {
        if (empty($data)) {
            return false;
        }
        $unpack = unpack('Cprotocol/Nwid/Nmid/Cdone', $data);
        if (!$unpack) {
            return false;
        }

        [
            'protocol' => $protocol
            , 'wid' =>  $worker_id
            , 'mid' => $message_id
            , 'done' => $done
        ] = $unpack;

        if (self::PROTOCOL_IPC !== $protocol) {
            throw new RuntimeException('unknown ipc message protocol: ' . $protocol);
        }

        $key = "{$protocol}-{$worker_id}-{$message_id}";
        $payload= substr($data, self::HEAD_LEN);
        if (isset($this->bufferSet[$key])) {
            $this->bufferSet[$key] .= $payload;
        } else {
            $this->bufferSet[$key] = $payload;
        }

        if ($done) {
            $result = $this->bufferSet[$key];
            unset($this->bufferSet[$key]);
            $result = ['ipc', $worker_id, $result];
        } else {
            $result = null;
        }
        return $result;
    }
}
