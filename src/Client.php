<?php
/**
 * @description rpc client
 *
 * @package
 *
 * @author kovey
 *
 * @time 2019-11-14 20:02:44
 *
 */
namespace Kovey\Rpc\Client;

use Kovey\Rpc\Client\Protocol\Json;
use Kovey\Library\Protocol\ProtocolInterface;
use Kovey\Rpc\Client\Event;
use Kovey\Event\Dispatch;
use Kovey\Event\Listener\Listener;
use Kovey\Event\Listener\ListenerProvider;

class Client
{
    const PACKET_MAX_LENGTH = 2097152;

    const TIME_OUT = 30;

    const NETWORK_ERROR = 100;

    private bool $isConnected = false;

    /**
     * @description events support
     *
     * @var Array
     */
    private static Array $events = array(
        'unpack' => Event\Unpack::class,
        'pack' => Event\Pack::class
    );

    /**
     * @description swoole client
     *
     * @var Swoole\Coroutine\Client
     */
    private \Swoole\Coroutine\Client $cli;

    /**
     * @description configs
     *
     * @var Array
     */
    private Array $configs;

    /**
     * @description client
     *
     * @var Array
     */
    private Array $conf;

    /**
     * @description current config index
     *
     * @var int
     */
    private int $current = 0;

    /**
     * @description unavailable configs
     *
     * @var Array
     */
    private Array $unavailables = array();

    /**
     * @description error info
     *
     * @var string
     */
    private string $error = '';

    /**
     * @description events listened
     *
     * @var Array
     */
    private Array $onEvents = array();

    /**
     * @description event dispatcher
     *
     * @var Dispatch
     */
    private Dispatch $dispatch;

    /**
     * @description event listener providero
     *
     * @var ListenerProvider
     */
    private ListenerProvider $provider;

    /**
     * @description construct
     *
     * @param Array $configs
     *
     * @return Client
     */
    public function __construct(Array $configs)
    {
        $this->cli = new \Swoole\Coroutine\Client(SWOOLE_SOCK_TCP);
        $this->cli->set(array(
            'open_length_check'     => true,
            'package_length_type'   => ProtocolInterface::PACK_TYPE,
            'package_length_offset' => ProtocolInterface::LENGTH_OFFSET,       //第N个字节是包长度的值
            'package_body_offset'   => ProtocolInterface::BODY_OFFSET,       //第几个字节开始计算长度
            'package_max_length'    => self::PACKET_MAX_LENGTH,  //协议最大长度
        ));

        $this->configs = $configs;
        $this->onEvents = array();
        $this->conf = array();
        $this->provider = new ListenerProvider();
        $this->dispatch = new Dispatch($this->provider);
    }

    /**
     * @description event listen
     *
     * @param string $event
     *
     * @param callable | Array $callable
     *
     * @return Client
     */
    public function on(string $event, Array | callable $callable) : Client
    {
        if (!isset(self::$events[$event])) {
            return $this;
        }

        if (!is_callable($callable)) {
            return $this;
        }

        $this->onEvents[$event] = $event;
        $listener = new Listener();
        $listener->addEvent(self::$events[$event], $callable);
        $this->provider->addListener($listener);

        return $this;
    }

    /**
     * @description connect to server
     *
     * @return bool
     */
    public function connect() : bool
    {
        if ($this->isConnected) {
            return $this->isConnected;
        }

        $this->error = '';
        $count = 0;
        do {
            $count ++;
            $conf = $this->getConf();
            if (empty($conf)) {
                $this->error .= 'connected failure to server, available config not found' . PHP_EOL;
                return false;
            }

            $this->conf = $conf;
            $result = $this->cli->connect($this->conf['host'], $this->conf['port']);
            if ($result || intval($this->cli->errCode) == 0) {
                $this->isConnected = true;
                return $this->isConnected;
            }

            $this->error .= sprintf('connected failure to server: %s:%s,error: %s', $this->conf['host'], $this->conf['port'], socket_strerror($this->cli->errCode)) . PHP_EOL;
            $this->unavailables[$this->current] = 1;
        } while ($count < 3);

        return false;
    }

    /**
     * @description get available config
     *
     * @return Array
     */
    private function getConf() : Array
    {
        $this->current = array_rand($this->configs, 1);
        if (!isset($this->unavailables[$this->current])) {
            return $this->configs[$this->current];
        }

        foreach ($this->configs as $index => $conf) {
            if (isset($this->unavailables[$index])) {
                continue;
            }

            $this->current = $index;
            return $conf;
        }

        return array();
    }

    /**
     * @description send data to server
     *
     * @param Array $data
     *
     * @return bool
     */
    public function send(Array $data) : bool
    {
        if (isset($this->onEvents['pack'])) {
            $data = $this->dispatch->dispatchWithReturn(new Event\Pack($data, $this->conf['secret_key'], $this->conf['encrypt_type'] ?? 'aes'));
        } else {
            $data = Json::pack($data, $this->conf['secret_key'], $this->conf['encrypt_type'] ?? 'aes', true);
        }

        if (!$data) {
            return false;
        }
        $result = $this->cli->send($data);
        if (!$result) {
            if ($this->cli->errCode >= self::NETWORK_ERROR) {
                $this->isConnected = false;
                if (!$this->connect()) {
                    return false;
                }

                $result = $this->cli->send($data);
            }

            if ($result === false) {
                $this->error = sprintf('send failure to server: %s:%s, error: %s', $this->conf['host'], $this->conf['port'], socket_strerror($this->cli->errCode));
            }
        }

        return $result;
    }

    /**
     * @description receive data from server
     *
     * @return Array
     */
    public function recv() : Array
    {
        $packet = $this->cli->recv(self::TIME_OUT);
        if (empty($packet)) {
            $this->isConnected = false;
            if ($packet === '') {
                $this->error = 'socket closed by server';
            } else if ($packet === false) {
                $this->error = socket_strerror($this->cli->errCode);
            }

            return array();
        }

        if (isset($this->onEvents['unpack'])) {
            $packet = $this->dispatch->dispatchWithReturn(new Event\Unpack($packet, $this->conf['secret_key'], $this->conf['encrypt_type'] ?? 'aes'));
        } else {
            $packet = Json::unpack($packet, $this->conf['secret_key'], $this->conf['encrypt_type'] ?? 'aes', true);
        }

        if (!is_array($packet)) {
            return array();
        }

        return $packet;
    }

    /**
     * @description get error info
     *
     * @return string
     */
    public function getError() : string
    {
        return $this->error;
    }

    /**
     * @description close connection
     *
     * @return void
     */
    public function close() : void
    {
        $this->cli->close();
    }

    public function __set(string $name, $val) : void
    {
        if (!isset($this->$name)) {
            return;
        }

        $this->$name = $val;
    }

    public function __destruct()
    {
        $this->cli->close();
    }
}
