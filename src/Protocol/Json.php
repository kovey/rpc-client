<?php
/**
 *
 * @description 传输协议
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

class Json implements ProtocolInterface
{
	/**
	 * @description 路径
	 *
	 * @var string
	 */
	private string $path;

	/**
	 * @description 方法
	 *
	 * @var string
	 */
	private string $method;

	/**
	 * @description 参数
	 *
	 * @var Array
	 */
	private Array $args;

	/**
	 * @description 包体类容
	 *
	 * @var string
	 */
	private string $body;

	/**
	 * @description 秘钥
	 *
	 * @var string
	 */
	private string $secretKey;

	/**
	 * @description 明文
	 *
	 * @var string
	 */
	private string $clear;

	/**
	 * @description 加密类型
	 *
	 * @var string
	 */
	private string $encryptType;

	/**
	 * @description 是否公钥
	 *
	 * @var bool
	 */
	private bool $isPub;

	/**
	 * @description 构造函数
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
	}

	/**
	 * @description 解析包
	 *
	 * @return bool
	 */
	public function parse() : bool
	{
		$this->clear = self::unpack($this->body, $this->secretKey, $this->encryptType, $this->isPub);

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

		return true;
	}

	/**
	 * @description 获取路径
	 *
	 * @return string
	 */
	public function getPath() : string
	{
		return $this->path;
	}

	/**
	 * @description 获取方法
	 *
	 * @return string
	 */
	public function getMethod() : string
	{
		return $this->method;
	}

	/**
	 * @description 获取参数
	 *
	 * @return Array
	 */
	public function getArgs() : Array
	{
		return $this->args;
	}

	/**
	 * @description 获取明文
	 *
	 * @return string
	 */
	public function getClear() : string
	{
		return $this->clear;
	}

	/**
	 * @description 打包
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
	public static function pack(Array $packet, string $secretKey, string $type = 'aes', bool $isPub = false) : string
	{
        $data = Encryption::encrypt(json_encode($packet), $secretKey, $type, $isPub);
        return pack(self::PACK_TYPE, strlen($data)) . $data;
	}

	/**
	 * @description 解包
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
        $info = unpack(self::PACK_TYPE, substr($data, self::LENGTH_OFFSET, self::HEADER_LENGTH));
        $length = $info[1] ?? 0;

        if (!Util::isNumber($length) || $length < 1) {
            throw ProtocolException('unpack packet failure', 1005, 'pack_error');
        }

        $encrypt = substr($data, self::BODY_OFFSET, $length);
        $packet = Encryption::decrypt($encrypt, $secretKey, $type, $isPub);

        return json_decode($packet, true);
	}
}
