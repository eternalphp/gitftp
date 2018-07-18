<?php
/**
 * This file is part of workerman.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author    walkor<walkor@workerman.net>
 * @copyright walkor<walkor@workerman.net>
 * @link      http://www.workerman.net/
 * @license   http://www.opensource.org/licenses/mit-license.php MIT License
 */
namespace Workerman\Protocols;

/**
 * Text Protocol.
 */
class BinaryTransfer
{
 // 协议头长度
    const PACKAGE_HEAD_LEN = 5;

    public static function input($recv_buffer)
    {
        // 如果不够一个协议头的长度，则继续等待
        if(strlen($recv_buffer) < self::PACKAGE_HEAD_LEN)
        {
            return 0;
        }
        // 解包
        $package_data = unpack('Ntotal_len/Cname_len', $recv_buffer);
        // 返回包长
        return $package_data['total_len'];
    }


    public static function decode($recv_buffer)
    {
        // 解包
        $package_data = unpack('Ntotal_len/Ccommand_len', $recv_buffer);
		$command_len = $package_data['command_len'];	
		$command   = substr($recv_buffer, self::PACKAGE_HEAD_LEN, $command_len);
		
        $buffer = substr($recv_buffer, self::PACKAGE_HEAD_LEN + $command_len);
		$buffer = gzuncompress($buffer);
		
		$data = json_decode($buffer,true);
		if(isset($data['access_token']) && $data['access_token'] != ''){
			$access_token = $data['access_token'];
			if(isset($data['data'])){
				$data = Security::decrypt($data['data'],$access_token);
				$data = json_decode($data,true);
				if(isset($data['file_data'])){
					$data['file_data'] = base64_decode($data['file_data']);
				}
			}
			$data['access_token'] = $access_token;
		}
		$data['command'] = $command;
		return $data;
    }

    public static function encode($data)
    {
        // 可以根据自己的需要编码发送给客户端的数据，这里只是当做文本原样返回
		if(is_array($data) && $data){
			$command = $data['command'];
			
			if(isset($data['access_token']) && $data['access_token'] != ''){
				$access_token = $data['access_token'];
				if(isset($data['data'])){
					$data['data'] = Security::encrypt(json_encode($data['data']),$access_token);
				}
				
				$data = json_encode($data);
				
			}else{
				$data = json_encode($data);
			}
			
			$data = gzcompress($data);
			
			$package = pack('NC', self::PACKAGE_HEAD_LEN  + strlen($command) + strlen($data), strlen($command)) . $command . $data;
			
			return $package;
		}else{
			return $data;
		}
    }
}


//加密解密
class Security{ 

	public static function encrypt($input, $key) {
		$size  = mcrypt_get_block_size(MCRYPT_RIJNDAEL_128, MCRYPT_MODE_ECB);
		$input = Security::pkcs5_pad($input, $size);
		$td    = mcrypt_module_open(MCRYPT_RIJNDAEL_128, '', MCRYPT_MODE_ECB, '');
		$iv    = mcrypt_create_iv(mcrypt_enc_get_iv_size($td), MCRYPT_RAND);
		mcrypt_generic_init($td, Security::hextobin($key), $iv);
		$data = mcrypt_generic($td, $input);
		mcrypt_generic_deinit($td);
		mcrypt_module_close($td);
		$data = base64_encode($data);
		return $data;
	}

	/*
	 * 解密
	 */

	public static function decrypt($sStr, $sKey) {
		$decrypted = mcrypt_decrypt(MCRYPT_RIJNDAEL_128, Security::hextobin($sKey), base64_decode($sStr), MCRYPT_MODE_ECB);
		$dec_s     = strlen($decrypted);
		$padding   = ord($decrypted[$dec_s - 1]);
		$decrypted = substr($decrypted, 0, -$padding);
		return $decrypted;
	}
	
	public static function pkcs5_pad($text, $blocksize) {
		$pad = $blocksize - (strlen($text) % $blocksize);
		return $text . str_repeat(chr($pad), $pad);
	}	
	
	public static function hextobin($hexstr) {
		$n    = strlen($hexstr);
		$sbin = "";
		$i    = 0;
		while ($i < $n) {
			$a = substr($hexstr, $i, 2);
			$c = pack("H*", $a);
			if ($i == 0) {
				$sbin = $c;
			} else {
				$sbin .= $c;
			}
			$i += 2;
		}
		return $sbin;
	}

}