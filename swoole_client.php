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