<?php
/**
 * @author    yuanzhongyi<kld230@163.com>
 * @copyright walkor<kld230@163.com>
 */
namespace Workerman\Lib;
use Workerman\Lib\SessionHandle;
use Workerman\Lib\Config;

Class Command{
	
	static $fileList = array();
	const LOG_PATH = 'logs';
	const LOG_FILE_SIZE = 10097152;
	const LOG_ON = true;
	
	//登录
	static function login($connection,$data){
		$username = $data['username'];
		$password = $data['password'];
		$account = Config::getConfig($username);
		if(!$account){
			self::sendMsg($connection,array(
				'errmsg'=>'No account has been set up, please contact the administrator ! \n'
			));
			self::addLog($username,array(
				'title'=>'登录验证',
				'errmsg'=>'No account has been set up, please contact the administrator ! '
			));
		}else{
			if($account['global.username'] == $username && MD5($account['global.password']) == $password){
				$sessionid = SessionHandle::sessionID($account['global.username'],$account['global.password']);
				SessionHandle::save($sessionid,array(
					'username'=>$username,
					'logintime'=>date("Y-m-d H:i:s")
				));
				self::sendMsg($connection,array(
					'command'=>$data['command'],
					'errcode'=>0,
					'errmsg'=>'login success',
					'access_token'=>$sessionid,
					'expires_in'=>7200
				));
				
				self::addLog($username,array(
					'title'=>'登录成功',
					'access_token'=>$sessionid,
				));
				
			}else{
				self::sendMsg($connection,array(
					'errcode'=>200,
					'errmsg'=>"User name and password error! \n"
				));
				
				self::addLog($username,array(
					'title'=>'登录失败',
					'errmsg'=>"User name and password error! \n"
				));
				
			}
		}
	}
	
	//上传文件到服务端
	public static function push($connection,$data){
		$access_token = $data['access_token'];

		$rootPath = SERVER_ROOT . Config::getServerPath($access_token);
		$save_path = $data['file_name'];
		if($save_path != ''){
			if(!file_exists($rootPath . dirname($save_path))){
				mkdir($rootPath . dirname($save_path),0777,true);
			}
			file_put_contents($rootPath . $save_path, $data['file_data']);
		}
		$data = array(
			'command'=>'push',
			'errmsg'=>"upload $save_path \n"
		);
		$connection->send($data);
		
		$username = SessionHandle::read($access_token,'username');
		self::addLog($username,array(
			'title'=>'上传文件到服务端',
			'filename'=>$rootPath . $save_path
		));
		
	}
	
	//下载文件到客户端
	public static function pull($connection,$data){
		$access_token = $data['access_token'];

		$rootPath = SERVER_ROOT . Config::getServerPath($access_token);
		if($data['path'] != '.'){
			$rootPath .= '/'.$data['path'];
		}
		
		$count = 0;
		Config::getFiles($rootPath,function($filename) use (&$count){
			self::$fileList[] = $filename;
			$count++;
		});

		$connection->onBufferFull = function($connection){
			$connection->bufferFull = true;
		};

		$connection->onBufferDrain = function($connection) use($access_token){
			
			$connection->bufferFull = false;
			self::sendClientData($connection,$access_token);
			
		};
		
		self::sendClientData($connection,$access_token);
	}
	
	//给客户端消息提示
	public static function sendMsg($connection,$data = array()){
		$dataMsg = array(
			'command' => 'login',
			'errcode' => -1,
			'errmsg'  => 'No account has been set up, please contact the administrator ! \n'
		);
		$data = array_merge($dataMsg,$data);
		$connection->send($data);
	}
	
	public static function authCheck($connection,$access_token){
		//验证是否登录
		if(!SessionHandle::read($access_token)){
			self::sendMsg($connection,array(
				'command'=>'loginOut',
				'errcode'=>200,
				'errmsg'=>"Please enter your username password ! \n"
			));
			return false;
		}
		return true;
	}
	
	//给客户端发送数据
	public static function sendClientData($connection,$access_token){
		
		if($connection->bufferFull == false){
			
			if(self::$fileList){
				
				$rootPath = Config::getServerPath($access_token);
				
				$filename = array_shift(self::$fileList);
				$file_data = file_get_contents($filename);

				$filenames = explode($rootPath,$filename);
				$filename = end($filenames);
				
				$data = array();
				$data['command'] = 'pull';
				$data['access_token'] = $access_token;
				$data['data'] = array(
					'file_name'=>$filename,
					'file_data'=>base64_encode($file_data)
				);
				
				$connection->send($data);
				
				$username = SessionHandle::read($access_token,'username');
				self::addLog($username,array(
					'title'=>'下载文件到客户端',
					'filename'=>$filename
				));

				self::sendClientData($connection,$access_token);
			}else{
				
				$connection->send("EOF"); //完整数据包结束标识
				return false;
			}
		}
	}
	
	public static function getLogPath($username){
		return implode('/',array(self::LOG_PATH,$username,date("Ymd"))) . '/';
	}
	
	public static function addLog($username,$data = array()){
		if(self::LOG_ON == true){
			$path = self::getLogPath($username);
			$filename = date("YmdH").".log";
			if(!file_exists($path)){
				mkdir($path,0777,true);
			}
			$data["handletime"] = date("Y-m-d H:i:s");
			if(file_exists($path . $filename)){
				if(filesize($path . $filename) > self::LOG_FILE_SIZE){
					rename($path . $filename, $path . $filename.'.bak');
				}
			}
			file_put_contents($path . $filename, serialize($data)."\n", FILE_APPEND);
		}
	}
}
