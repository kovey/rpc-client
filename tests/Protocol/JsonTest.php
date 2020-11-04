<?php
/**
 * @description
 *
 * @package
 *
 * @author kovey
 *
 * @time 2020-11-03 09:53:51
 *
 */
namespace Kovey\Rpc\Client\Protocol;

use PHPUnit\Framework\TestCase;
use Kovey\Library\Encryption\Encryption;

class JsonTest extends TestCase
{
    public function testPackAnUnpackAes()
    {
        $data = array(
            'kovey' => 'framework',
            'rpc' => 'server',
            'time' => time()
        );

        $key = md5('123456');

        $encrypt = Json::pack($data, $key);
        $this->assertTrue(!empty($encrypt));

        $decrypt = Json::unpack($encrypt, $key);
        $this->assertEquals($data, $decrypt);
    }

    public function testPackAnUnpackRsa()
    {
        $data = array(
            'kovey' => 'framework',
            'rpc' => 'server',
            'time' => time()
        );
        $encrypt = Json::pack($data, __DIR__ . '/../crt/public.pem', 'rsa', true);
        $this->assertTrue(!empty($encrypt));

        $decrypt = Json::unpack($encrypt, __DIR__ . '/../crt/private.pem', 'rsa', false);
        $this->assertEquals($data, $decrypt);
    }

    public function testUnpackToJsonObject()
    {
        $data = array(
            'p' => 'Kovey',
            'm' => 'framework',
            'a' => array('test', array('a' => 'b'))
        );
        $encrypt = Json::pack($data, __DIR__ . '/../crt/public.pem', 'rsa', true);
        $this->assertTrue(!empty($encrypt));

        $json = new Json($encrypt, __DIR__ . '/../crt/private.pem', 'rsa', false);
        $this->assertTrue($json->parse());
        $this->assertEquals('Kovey', $json->getPath());
        $this->assertEquals('framework', $json->getMethod());
        $this->assertEquals(array('test', array('a' => 'b')), $json->getArgs());
    }
}
