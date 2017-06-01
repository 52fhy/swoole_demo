<?php
$server = new swoole_websocket_server("0.0.0.0", 9501);

$server->set(array(
	'daemonize' => false,
	'worker_num' => 2,
));

$server->on('Start', function (swoole_websocket_server $server) {
    echo "Server Start... \n";
    swoole_set_process_name("swoole_websocket_server");
});

$server->on('ManagerStart', function (swoole_websocket_server $server) {
    echo "ManagerStart\n";
});

$server->on('WorkerStart', function (swoole_websocket_server $server, $worker_id) {
    echo "WorkerStart \n";
    if ($server->worker_id == 0){
        swoole_timer_tick(10000,function($id) use ($server) {
            echo "test timer\n";
        });
    }
});

$server->on('Open', function (swoole_websocket_server $server, $request) {
    echo "server: handshake success with fd{$request->fd}\n";
});

$server->on('Message', function (swoole_websocket_server $server, $frame) {
    echo "receive from {$frame->fd}:{$frame->data},opcode:{$frame->opcode},fin:{$frame->finish}\n";
    $server->push($frame->fd, "this is server");
});

$server->on('Close', function ($ser, $fd) {
    echo "client {$fd} closed\n";
});

$server->start();