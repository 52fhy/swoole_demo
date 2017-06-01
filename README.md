# swoole_demo
swoole_demo

## WebSocket服务器

使用Swoole可以很简单的搭建异步非阻塞多进程的WebSocket服务器。

### WebSocket服务器

``` php
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
```

shell里直接运行`php swoole_ws_server.php`启动即可。如果设置了后台运行，可以使用下列命令强杀进程：
```
kill -9 $(ps aux|grep swoole|grep -v grep|awk '{print $2}')
```
或者重新启动worker进程：
```
kill -10 $(ps aux|grep swoole_websocket_server|grep -v grep|awk '{print $2}')
```
输出：
```
[2017-06-01 22:06:21 $2479.0]	NOTICE	Server is reloading now.
WorkerStart 
WorkerStart 
```

注意：

- onMessage回调函数为必选，当服务器收到来自客户端的数据帧时会回调此函数。
```
/**
* @param $server
* @param $frame 包含了客户端发来的数据帧信息；使用$frame->fd获取fd；$frame->data获取数据内容
*/
function onMessage(swoole_server $server, swoole_websocket_frame $frame)
```
- 使用`$server->push()`向客户端发送消息。长度最大不得超过2M。发送成功返回true，发送失败返回false。
```
function swoole_websocket_server->push(int $fd, string $data, int $opcode = 1, bool $finish = true);
```

### WebSocket客户端
最简单的是使用JS编写：
``` js
socket = new WebSocket('ws://192.168.1.107:9501/'); 
socket.onopen = function(evt) { 
    // 发送一个初始化消息
    socket.send('I am the client and I\'m listening!'); 
}; 

// 监听消息
socket.onmessage = function(event) { 
    console.log('Client received a message', event); 
}; 

// 监听Socket的关闭
socket.onclose = function(event) { 
    console.log('Client notified socket has closed',event); 
}; 

socket.onerror = function(evt) { 
    console.log('Client onerror',event); 
}; 
```

Swoole里没有直接提供swoole_websocket客户端，不过通过引入[WebSocketClient.php](https://github.com/52fhy/swoole_demo/blob/master/WebSocketClient.php)文件可以实现：
```
<?php

require_once __DIR__ . '/WebSocketClient.php';

$client = new WebSocketClient('192.168.1.107', 9501);

if (!$client->connect())
{
	echo "connect failed \n";
	return false;
}

$send_data = "I am client.\n";
if (!$client->send($send_data))
{
	echo $send_data. " send failed \n";
	return false;
}

echo "send succ \n";
return true;
```
上面代码实现的是一个同步的swoole_websocket客户端。发送完消息会自动关闭，可以用来与php-fpm应用协作：将耗时任务使用客户端发送到swoole_websocket_server。
