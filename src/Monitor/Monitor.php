<?php
/**
 *
 * @description 系统监控进程
 *
 * @package     Kovey\Rpc\Client
 *
 * @time        2020-01-19 14:52:47
 *
 * @author      kovey
 */
namespace Kovey\Rpc\Client\Monitor;

use Kovey\Rpc\Client\Client;
use Kovey\Library\Util\Json;

class Monitor
{
    private Client $cli;

    private string $error = '';

    private string $project;

    private string $from;

    public function __construct(Array $config, string $project, string $from)
    {
        $this->cli = new Client($config);
        $this->project = $project;
        $this->from = $from;
    }

    /**
     * @description 业务处理
     *
     * @return null
     */
    public function sendToMonitor(Array $logger) : bool
    {
        if (!$this->cli->connect()) {
            $this->error = $this->cli->getError();
            return false;
        }

        if (!$this->cli->send(array(
            'p' => 'Monitor',
            'm' => 'save',
            'a' => array(Json::encode($logger), $this->project),
            't' => hash('sha256', uniqid('monitor', true) . random_int(0, 9999999)),
            'f' => $this->from
        ))) {
            $this->error = $this->cli->getError();
            return false;
        }

        $result = $this->cli->recv();

        if (empty($result)) {
            $this->error = 'response error';
            return false;
        }

        if ($result['code'] > 0) {
            if ($result['type'] != 'success') {
                $this->error = $result['err'] . PHP_EOL . $result['trace'];
                return false;
            }
        }

        return true;
    }

    public function getError() : string
    {
        return $this->error;
    }
}
