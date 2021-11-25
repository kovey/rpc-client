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
use Kovey\Rpc\Client\Version\Version;
use Kovey\Library\Trace\TraceInterface;

abstract class ServiceAbstract implements TraceInterface
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
     * @description timeout
     *
     * @var int
     */
    protected int $timeout = 0;

    /**
     * @description trace id
     *
     * @var string
     */
    public string $traceId;

    /**
     * @description span id
     *
     * @var string
     */
    public string $spanId;

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
            't' => $this->traceId,
            'f' => $this->getCurrentServiceName(),
            's' => $this->spanId,
            'v' => Version::VERSION
        ))) {
            throw new ProtocolException($this->cli->getError(), 1003, 'send_error');
        }

        $result = $this->cli->recv($this->timeout);
        if (empty($result)) {
            throw new ProtocolException('resopone is error: ' . $this->cli->getError(), 1000, 'request_error');
        }

        if ($result['type'] === 'success') {
            return $result['result'];
        }

        if ($result['type'] === 'busi_exception') {
            throw new BusiException($result['err'], $result['code']);
        }

        throw new ProtocolException($result['err'], $result['code'], $result['type'], $result['trace'] ?? '');
    }

    /**
     * @description set trace id
     *
     * @param string $traceId
     */
    public function setTraceId(string $traceId) : void
    {
        $this->traceId = $traceId;
    }

    /**
     * @description set span id
     *
     * @param string $spanId
     */
    public function setSpanId(string $spanId) : void
    {
        $this->spanId = $spanId;
    }

    /**
     * @description get trace id
     *
     * @return string
     */
    public function getTraceId() : string
    {
        return $this->traceId;
    }

    /**
     * @description get trace id
     *
     * @return string
     */
    public function getSpanId() : string
    {
        return $this->spanId;
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
