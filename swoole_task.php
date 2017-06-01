<?php
$server = new \swoole_server("127.0.0.1",8088);

$server->set(array(
	'daemonize' => true,
	'reactor_num' => 2,
	'worker_num' => 1,
	'task_worker_num' => 1,
	'log_file' => '/www/log/swoole.log',
));

$server->on('start', function ($serv){ 
	swoole_set_process_name("swoole_task_matser"); 
});

$server->on('connect', function ($serv, $fd){ 
	echo "client connect. fd is {$fd}\n";
});

$server->on('receive', function ($serv, $fd, $from_id, $data){
	
	echo sprintf("onReceive. fd: %d , data: %s\n", $fd, json_encode($data) );
	
	$serv->task(json_encode([
		'fd' => $fd,
		'task_name' => 'send_email',
		'email_content' => $data,
		'email' => 'admin@qq.com'
	]));
});

$server->on('close', function ($serv, $fd){
	echo "client close. fd is {$fd}\n";
});

$server->on('task', function (swoole_server $serv, $task_id, $from_id,  $data){
	echo $data;
	
	$data = json_decode($data, true);
	$serv->send($data['fd'], "send eamil to {$data['email']}, content is : {$data['email_content']}\n");
	
	//echo 'task finished';
	//return 'task finished';
	$serv->finish('task finished');
});

$server->on('finish', function (swoole_server $serv, $task_id, $data){
	echo 'onFinish:' .$data;
});


$server -> start();