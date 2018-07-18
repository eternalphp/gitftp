<?php
/**
 * @author    yuanzhongyi<kld230@163.com>
 * @copyright walkor<kld230@163.com>
 */
namespace Workerman\Lib;
use Workerman\Lib\SessionHandle;

Class Config{
	
	public static function getFiles($path,$callback){
		if(is_dir($path)){
			$handle = dir($path);
			while($filename = $handle->read()){
				if($filename != '.' && $filename != '..'){
					$path = rtrim($path,'/') . '/';
					if(is_dir($path . $filename)){
						self::getFiles($path . $filename,$callback);
					}else{
						$filename = $path . $filename;
						$callback($filename);
					}
				}
			}
			$handle->close();
		}else{
			$callback($path);
		}
	}

	public static function getConfigPath($username){
		return implode('/',array('home',$username,'config'));
	}

	public static function getConfig($username){
		$configPath = self::getConfigPath($username);
		if(!file_exists($configPath)){
			return false;
		}else{
			$config = array();
			$lines = file($configPath);
			if($lines){
				foreach($lines as $k=>$line){
					list($key,$value) = explode(' = ',$line);
					$config[trim($key)] = trim($value);
				}
			}
			return $config;
		}
	}

	public static function getServerPath($access_token){
		$username = SessionHandle::read($access_token,'username');
		if($username){
			$config = self::getConfig($username);
			return $config['global.serverPath'];
		}
	}
}
