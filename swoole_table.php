<?php
$table = new swoole_table(1024);
$table->column('id', swoole_table::TYPE_INT, 4);
$table->column('uid', swoole_table::TYPE_INT, 8);
$table->column('name', swoole_table::TYPE_STRING, 64);
$table->create();

$table->set(2, array('id' => 2, 'uid' => 2, 'name' => 'yjc'));
var_dump($table->get(2));


$server = new swoole_websocket_server("0.0.0.0", 9501);

$server->set(array(
	'daemonize' => false,
	'worker_num' => 2,
));

$server->on('Start', function (swoole_websocket_server $server) {
    echo "Server Start... \n";
    swoole_set_process_name("swoole_websocket_server");
});

$server->on('WorkerStart', function (swoole_websocket_server $server, $worker_id) {
    echo "WorkerStart \n";
});

$server->on('Open', function (swoole_websocket_server $server, $request) {
	var_dump($request);
    echo "new Client: fd{$request->fd}\n";
});

$server->on('Message', function (swoole_websocket_server $server, $frame) {
    echo "receive from {$frame->fd}:{$frame->data},opcode:{$frame->opcode},fin:{$frame->finish}\n";
    $server->push($frame->fd, "this is server");
});

$server->on('Close', function ($ser, $fd) {
    echo "client {$fd} closed\n";
});

$server->start();
