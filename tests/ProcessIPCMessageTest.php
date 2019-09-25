<?php
declare(strict_types=1);

namespace unzxin\zswCore\Tests;

use PHPUnit\Framework\TestCase;
use unzxin\zswCore\Process\IPCMessageProtocol;

class ProcessIPCMessageTest extends TestCase
{
    public function testMsgChunkGenerate()
    {
        $ipc = new IPCMessageProtocol();
        $ipc->setChunkSize(32);

        $message = str_repeat('123456', 16);

        $recv = '';
        foreach ($ipc->generateMsgChunk(32, $message) as $chunk) {
            $recv = $ipc->read($chunk);
        }

        $this->assertEquals(['ipc', 32, $message], $recv);
    }
}
