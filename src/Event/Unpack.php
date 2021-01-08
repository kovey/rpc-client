<?php
/**
 * @description
 *
 * @package
 *
 * @author kovey
 *
 * @time 2021-01-08 10:02:48
 *
 */
namespace Kovey\Rpc\Client\Event;

use Kovey\Event\EventInterface;

class Unpack implements EventInterface
{
    private string $packet;

    private string $key;

    private string $type;

    public function __construct(string $packet, string $key, string $type)
    {
        $this->packet = $packet;
        $this->key = $key;
        $this->type = $type;
    }

    public function getPacket() : string
    {
        return $this->packet;
    }

    public function getKey() : string
    {
        return $this->key;
    }

    public function getType() : string
    {
        return $this->type;
    }

    /**
     * @description propagation stopped
     *
     * @return bool
     */
    public function isPropagationStopped() : bool
    {
        return true;
    }

    /**
     * @description stop propagation
     *
     * @return EventInterface
     */
    public function stopPropagation() : EventInterface
    {
        return $this;
    }
}
