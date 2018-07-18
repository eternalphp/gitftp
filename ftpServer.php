<?php 

use Workerman\Worker;
use \Workerman\Lib\Timer;
use \Workerman\Lib\SessionHandle;
use \Workerman\Lib\Command;
require_once 'Workerman/Autoloader.php';

define("SERVER_ROOT",dirname(__DIR__).'/'); //定义根目录

$worker = new Worker('BinaryTransfer://0.0.0.0:8333');
$worker->count = 10;

$worker->onWorkerStart = function($connection){
    $time_interval = 10;
    Timer::add($time_interval, function(){
        SessionHandle::gc();
    });
};

$worker->onMessage = function($connection, $data)
{
	
	SessionHandle::session_start();
	
	$connection->bufferFull = false;
	$command = $data['command'];
	
	switch($command){
		case 'login':
			Command::login($connection,$data);
		break;
		case 'push':
			$access_token = $data['access_token'];
			if(!Command::authCheck($connection,$access_token)){
				return false;
			}
			Command::push($connection,$data);
		break;
		case 'pull':
			$access_token = $data['access_token'];
			if(!Command::authCheck($connection,$access_token)){
				return false;
			}
			Command::pull($connection,$data);
		break;
	}
	
};

$worker->onError = function($connection, $code, $msg)
{
    echo "error $code $msg\n";
};

$worker->onClose = function($connection)
{
    echo "connection closed\n";
};

$worker->onConnect = function($connection)
{
    echo "new connection from ip " . $connection->getRemoteIp() . "\n";
};

$worker->onWorkerReload = function($worker)
{
    foreach($worker->connections as $connection)
    {
        $connection->send('worker reloading');
    }
};

Worker::runAll();

?>