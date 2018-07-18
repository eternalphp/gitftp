<?php
/**
 * @author    yuanzhongyi<kld230@163.com>
 * @copyright walkor<kld230@163.com>
 */
namespace Workerman\Lib;

Class SessionHandle{
	
	static $maxlifetime = 1440;
	static $gc_probability = 1;
	static $gc_divisor = 20;
	static $save_path = 'session/';
	static $clients = 0; //客户端连接数
	
	static function session_start(){
		self::$clients ++ ;
	}
	
	static function sessionID($username,$password){
		return md5(implode("",array($username,$password,time())));
	}
	
	static function save($sessionid,$data){
		$path = self::$save_path . $sessionid;
		file_put_contents($path,serialize($data));
	}
	
	static function read($sessionid,$name = null){
		if($sessionid != ''){
			$path = self::$save_path . $sessionid;
			if(!file_exists($path)){
				return false;
			}else{
				$data = file_get_contents($path);
				$data = unserialize($data);
				
				//判断是否过期
				$createtime = filectime($path);
				if(isset($data['expires_in']) && $data['expires_in']>0){
					if((time() - $createtime)>$data['expires_in']){
						unlink($path);
						return false;
					}
				}else{
					if((time() - $createtime)>self::$maxlifetime){
						unlink($path);
						return false;
					}
				}
				if($name != null){
					return $data[$name];
				}else{
					return $data;
				}
			}
		}else{
			return false;
		}
	}
	
	static function destroy($sessionid){
		$path = self::$save_path . $sessionid;
		if(!file_exists($path)){
			return true;
		}else{
			return unlink($path);
		}
	}
	
	static function gc(){
		if(self::$clients >0 && ((self::$gc_probability / self::$gc_divisor) * self::$clients) == 1){
		
			$handle = dir(self::$save_path);
			while($file = $handle->read()){
				if($file != '.' && $file != '..'){
					$data = self::read($file);
					if($data){
						$createtime = filectime(self::$save_path . $file);
						if((time() - $createtime) > self::$maxlifetime){
							self::destroy($file);
						}
					}
				}
			}
			$handle->close();
			self::$clients = 0;
		}
	}
}
