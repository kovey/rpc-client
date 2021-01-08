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
        $key = 'U0ZLf0s8NQgggMrBi16KDeUyPBgzLPWALhohHKRRyxK';
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
             ), $key));
    }

    public function testClientWithOn()
    {
        $key = 'U0ZLf0s8NQgggMrBi16KDeUyPBgzLPWALhohHKRRyxK';
        $cli = new Client(array(array(
            'host' => '127.0.0.1',
            'port' => 9901,
            'secret_key' => $key,
            'encrypt_type' => 'aes'
        )));
        $cli->cli = $this->client;
        $this->assertInstanceOf(Client::class, $cli->on('pack', function (Event\Pack $event) {
            $this->assertEquals(array('p' => 'kovey', 'm' => 'framework', 'a' => array(), 't' => '12335'), $event->getPacket());
            $this->assertEquals('U0ZLf0s8NQgggMrBi16KDeUyPBgzLPWALhohHKRRyxK', $event->getKey());
            $this->assertEquals('aes', $event->getType());
            return 'aaaaa';
        }));
        $cli->on('unpack', function (Event\Unpack $event) {
            $this->assertEquals('AAAAbFd3VC92ZllocHJNNmZVcHZ6RjgxT21peGV4NEZBRmp1cytKUy9JUlRNQ2JuQng1cStNOGJkYVptWG5YWDFSbjhtMG5ud1lnZ3B3bStMYXZVVnUyWHBlSDNJL1RBbDdZVkUzRlpHcmdyZTdVPQ==', base64_encode($event->getPacket()));
            $this->assertEquals('U0ZLf0s8NQgggMrBi16KDeUyPBgzLPWALhohHKRRyxK', $event->getKey());
            $this->assertEquals('aes', $event->getType());
            return array(
                'err' => '',
                'type' => 'success',
                'result' => '{"kovey":"framework"}',
                'code' => 0
            );
        });

        $this->assertTrue($cli->connect());
        $this->assertTrue($cli->send(array('p' => 'kovey', 'm' => 'framework', 'a' => array(), 't' => '12335')));
        $this->assertEquals(array(
            'err' => '',
            'type' => 'success',
            'result' => '{"kovey":"framework"}',
            'code' => 0
        ), $cli->recv());
    }

    public function testClient()
    {
        $key = 'U0ZLf0s8NQgggMrBi16KDeUyPBgzLPWALhohHKRRyxK';
        $cli = new Client(array(array(
            'host' => '127.0.0.1',
            'port' => 9901,
            'secret_key' => $key,
            'encrypt_type' => 'aes'
        )));

        $cli->cli = $this->client;
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
