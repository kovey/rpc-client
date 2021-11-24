<?php
/**
 * @description rpc protocol
 *
 * @package     Protocol
 *
 * @time        2019-11-16 18:14:53
 *
 * @author      kovey
 */
namespace Kovey\Rpc\Client\Protocol;

use Kovey\Library\Util\Util;
use Kovey\Library\Encryption\Encryption;
use Kovey\Library\Exception\ProtocolException;
use Kovey\Library\Protocol\ProtocolInterface;
use Kovey\Library\Util\Json as JS;

class Json implements ProtocolInterface
{
    /**
     * @description path
     *
     * @var string
     */
    private string $path = '';

    /**
     * @description method
     *
     * @var string
     */
    private string $method = '';

    /**
     * @description arguments
     *
     * @var Array
     */
    private Array $args = array();

    /**
     * @description body
     *
     * @var string
     */
    private string $body = '';

    /**
     * @description secret key
     *
     * @var string
     */
    private string $secretKey;

    /**
     * @description clear text
     *
     * @var Array
     */
    private Array $clear = array();

    /**
     * @description encrypt type
     *
     * @var string
     */
    private string $encryptType;

    /**
     * @description is public key
     *
     * @var bool
     */
    private bool $isPub;

    /**
     * @description trace Id
     *
     * @var string
     */
    private string $traceId = '';

    /**
     * @description from
     *
     * @var string
     */
    private string $from = '';

    /**
     * @description client program language
     *
     * @var string
     */
    private string $clientLang;

    /**
     * @description spanId
     *
     * @var string
     */
    private string $spanId = '';

    /**
     * @description client version
     *
     * @var string
     */
    private string $version;

    /**
     * @description compress
     *
     * @var string
     */
    private string $compress;

    /**
     * @description construct
     *
     * @param string $body
     *
     * @param string $key
     *
     * @param string $type
     *
     * @param bool $isPub
     *
     * @return Json
     */
    public function __construct(string $body, string $key, string $type = 'aes', bool $isPub = false)
    {
        $this->body = $body;
        $this->secretKey = $key;
        $this->encryptType = $type;
        $this->isPub = $isPub;
        $this->compress = self::COMPRESS_NO;
    }

    /**
     * @description parse body
     *
     * @return bool
     */
    public function parse() : bool
    {
        $info = self::unpack($this->body, $this->secretKey, $this->encryptType, $this->isPub);
        $this->compress = $info['compress'];
        $this->clear = $info['packet'];

        if (!is_array($this->clear)) {
            return false;
        }

        if (!isset($this->clear['p'])
            || !isset($this->clear['m'])
            || empty($this->clear['p'])
            || empty($this->clear['m'])
        ) {
            return false;
        }

        if (isset($this->clear['a']) && !is_array($this->clear['a'])) {
            return false;
        }

        $this->path  = $this->clear['p'];
        $this->method = $this->clear['m'];
        $this->args = $this->clear['a'] ?? array();
        $this->from = $this->clear['f'] ?? '';
        $this->traceId = $this->clear['t'] ?? '';
        $this->spanId = $this->clear['s'] ?? '';

        return true;
    }

    /**
     * @description get path
     *
     * @return string
     */
    public function getPath() : string
    {
        return $this->path;
    }

    /**
     * @description get method
     *
     * @return string
     */
    public function getMethod() : string
    {
        return $this->method;
    }

    /**
     * @description get arguments
     *
     * @return Array
     */
    public function getArgs() : Array
    {
        return $this->args;
    }

    /**
     * @description get clear text
     *
     * @return string
     */
    public function getClear() : string
    {
        return JS::encode($this->clear);
    }

    /**
     * @description get traceId
     *
     * @return string
     */
    public function getTraceId() : string
    {
        return $this->traceId;
    }

    /**
     * @description get from
     *
     * @return string
     */
    public function getFrom() : string
    {
        return $this->from;
    }

    /**
     * @description get client language
     *
     * @return string
     */
    public function getClientLang() : string
    {
        return $this->clientLang;
    }

    /**
     * @description get client version
     *
     * @return string
     */
    public function getVersion() : string
    {
        return $this->version;
    }

    /**
     * @description package
     *
     * @param Array $packet
     *
     * @param string $secretKey
     *
     * @param string $type
     *
     * @param bool $isPub
     *
     * @return string
     */
    public static function pack(Array $packet, string $secretKey, string $type = 'aes', bool $isPub = false, int $compress = self::COMPRESS_NO) : string
    {
        $data = Encryption::encrypt(json_encode($packet), $secretKey, $type, $isPub);
        if ($compress == self::COMPRESS_GZIP) {
            $data = gzcompress($data);
            return pack(self::PACK_TYPE, strlen($data)) . pack(self::PACK_TYPE, $compress) . $data;
        }

        return pack(self::PACK_TYPE, strlen($data)) . $data;
    }

    /**
     * @description unpackage
     *
     * @param string $data
     *
     * @param string $secretKey
     *
     * @param string $type
     *
     * @param bool $isPub
     *
     * @return Array
     */
    public static function unpack(string $data, string $secretKey, string $type = 'aes', bool $isPub = false) : Array
    {
        $info = unpack(self::PACK_TYPE . 'a/' . self::PACK_TYPE . 'b', substr($data, self::LENGTH_OFFSET, self::HEADER_LENGTH_NEW));
        $compress = $info['b'];
        if ($compress != self::COMPRESS_GZIP) {
            $info = unpack(self::PACK_TYPE . 'a', substr($data, self::LENGTH_OFFSET, self::HEADER_LENGTH));
            $compress = self::COMPRESS_NO;
        }

        $length = $info['a'];

        if (!Util::isNumber($length) || $length < 1) {
            throw new ProtocolException('unpack packet failure', 1005, 'pack_error');
        }

        $encrypt = substr($data, $compress == self::COMPRESS_NO ? self::BODY_OFFSET : self::HEADER_LENGTH_NEW, $length);
        if ($compress == self::COMPRESS_GZIP) {
            $encrypt = gzuncompress($encrypt, self::COMPRESS_LENGTH);
        }
        $packet = Encryption::decrypt($encrypt, $secretKey, $type, $isPub);

        return array(
            'compress' => $compress,
            'packet' => json_decode($packet, true)
        );
    }

    public function getSpanId() : string
    {
        return $this->spanId;
    }

    public function getCompress() : int
    {
        return $this->compress;
    }
}
