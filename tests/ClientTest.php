<?php
/**
 * @description
 *
 * @package
 *
 * @author kovey
 *
 * @time 2020-11-04 11:08:25
 *
 */
namespace Kovey\Rpc\Client;

use PHPUnit\Framework\TestCase;
use Kovey\Rpc\Client\Protocol\Json;

class ClientTest extends TestCase
{
    protected $client;

    protected function setUp() : void
    {
        $this->client = $this->createMock(\Swoole\Coroutine\Client::class);
        $this->client->method('connect')
             ->willReturn(true);
        $this->client->method('send')
             ->willReturn(true);
        $this->client->method('close')
            ->willReturn(true);
        $this->client->method('recv')
             ->willReturn(Json::pack(array(
                 'err' => '',
                 'type' => 'success',
                 'result' => '{"kovey":"framework"}',
                 'code' => 0
             ), md5('123456')));
    }

    public function testClient()
    {
        $cli = new Client(array(array(
            'host' => '127.0.0.1',
            'port' => 9901,
            'secret_key' => md5('123456'),
            'encrypt_type' => 'aes'
        )));
        $cli->cli = $this->client;
        $this->assertInstanceOf(Client::class, $cli->on('test', function () {}));
        $this->assertTrue($cli->connect());
        $this->assertTrue($cli->send(array('p' => 'kovey', 'm' => 'framework', 'a' => array(), 't' => '12335')));
        $this->assertEquals(array(
            'err' => '',
            'type' => 'success',
            'result' => '{"kovey":"framework"}',
            'code' => 0
        ), $cli->recv());
    }
}
