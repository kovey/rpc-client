<?php
/**
 * @description Rpc client object
 *
 * @package
 *
 * @author kovey
 *
 * @time 2019-11-14 22:22:00
 *
 */
namespace Kovey\Rpc\Client\Service;

use Kovey\Library\Exception\ProtocolException;
use Kovey\Library\Exception\BusiException;
use Kovey\Rpc\Client\Client;

#[\Attribute]
abstract class ServiceAbstract
{
    /**
     * @description client
     *
     * @var Kovey\Rpc\Client\Client
     */
    private Client $cli;

    /**
     * @description config
     *
     * @var Array
     */
    private Array $conf;

    /**
     * @description construct
     *
     * @param Array $conf
     *
     * @return ServiceAbstract
     */
    public function __construct(Array $conf)
    {
        $this->cli = new Client($conf);
        $this->conf = $conf;
    }

    /**
     * @description request method of server
     *
     * @param string $method
     *
     * @param Array $args
     *
     * @return mixed
     *
     * @throws ProtocolException
     */
    public function __call(string $method, Array $args) : mixed
    {
        if (!$this->cli->connect()) {
            throw new ProtocolException($this->cli->getError(), 1002, 'connect_error');
        }

        if (!$this->cli->send(array(
            'p' => $this->getServiceName(),
            'm' => $method,
            'a' => $args,
            't' => $this->traceId ?? '',
            'f' => $this->getCurrentServiceName(),
            's' => $this->spanId ?? ''
        ))) {
            throw new ProtocolException($this->cli->getError(), 1003, 'send_error');
        }

        $result = $this->cli->recv();
        if (empty($result)) {
            throw new ProtocolException('resopone is error.', 1000, 'request_error');
        }

        if ($result['type'] === 'success') {
            return $result['result'];
        }

        if ($result['type'] === 'busi_exception') {
            throw new BusiException($result['code'], $result['err']);
        }

        throw new ProtocolException($result['err'], $result['code'], $result['type'], $result['trace'] ?? '');
    }

    /**
     * @description get service name
     *
     * @return string
     */
    abstract protected function getServiceName() : string;

    /**
     * @description get current service name
     *
     * @return string
     */
    abstract protected function getCurrentServiceName() : string;
}
