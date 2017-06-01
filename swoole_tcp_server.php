<?php
$server = new \swoole_server("127.0.0.1",8088);

$server->set(array(
	'daemonize' => false,
	'reactor_num' => 2,
	'worker_num' => 4
));

$server->on('connect', function ($serv, $fd){ 
	echo "client connect. fd is {$fd}\n";
});

$server->on('receive', function ($serv, $fd, $from_id, $data){
	echo "client connect. fd is {$fd}\n";
});

$server->on('close', function ($serv, $fd){
	echo "client close. fd is {$fd}\n";
});

// 以下回调发生在Master进程
$server->on("start", function (\swoole_server $server){
	echo "On master start.\n";
});
$server->on('shutdown', function (\swoole_server $server){
	echo "On master shutdown.\n";
});

// 以下回调发生在Manager进程
$server->on('ManagerStart', function (\swoole_server $server){
	echo "On manager start.\n";
});
$server->on('ManagerStop', function (\swoole_server $server){
	echo "On manager stop.\n";
});

// 以下回调也发生在Worker进程
$server->on('WorkerStart', function (\swoole_server $server, $worker_id){
	echo "Worker start\n";
});
$server->on('WorkerStop', function(\swoole_server $server, $worker_id){
	echo "Worker stop\n";
});
$server->on('WorkerError', function(\swoole_server $server, $worker_id, $worker_pid, $exit_code){
	echo "Worker error\n";
});

$server -> start();