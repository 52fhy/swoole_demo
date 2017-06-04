<?php
class Server{
    private  $server;

    private $process;
	
	private $fd;

    private $async_process = [];

    public function __construct()
    {
        $this->server = new swoole_websocket_server('0.0.0.0', 9501);
        $this->server->set(array(
            'worker_num' => 2,
            'dispatch_mode' => 2,
            'daemonize' => 0,
        ));

        $this->server->on('message', array($this, 'onMessage'));
        $this->server->on('open', array($this, 'onOpen'));
//        $this->server->on('request', array($this, 'onRequest'));
        $this->server->on('workerstart', array($this, 'onWorkerStart'));
		$this->server->on('close', array($this, 'onClose'));

		//该进程默认用来处理运行后直接退出的命令
        $this->process = new swoole_process(array($this, 'onProcess'), true);//第3个参数数设置为true，进程内echo将不是打印屏幕，写入到管道。
        $this->server->addProcess($this->process);//添加一个用户自定义的工作进程。
        $this->server->start();
    }

    public function onWorkerStart($server, $worker_id){
		
		echo "onWorkerStart:{$worker_id}.\n";
		
		//在异步信号回调中执行wait回收子进程 https://wiki.swoole.com/wiki/page/220.html
		//子进程结束必须要执行wait进行回收，否则子进程会变成僵尸进程
        swoole_process::signal(SIGCHLD, function ($sig){//在一个进程终止或者停止时，将SIGCHLD信号发送给其父进程
            while ($ret = swoole_process::wait(false)){//swoole_process::wait 回收结束运行的子进程
                echo "PID={$ret['pid']} exit.\n";
				unset($this->async_process[$this->fd]);
            }
        });
    }
	
	public function onOpen($server, $req){
		echo "new client: {$req->fd}\n";
	}

    public function onMessage($server, $frame){
		$this->fd = $frame->fd;
		echo $this->fd . "\n";
        echo $frame->data . "\n";
		$this->server->push($frame->fd, "onReceived: ".$frame->data);
		
        $data = json_decode($frame->data, true);
        $cmd = $data['cmd'];
        $is_block = isset($data['is_block']) ? $data['is_block'] : 0;//标注进程类型
        if($is_block){
            if(isset($this->async_process[$frame->fd])){
                $process = $this->async_process[$frame->fd];//这里有个Bug，如果子进程调用$worker->exit()退出后，这里fd还是不变的
            }else{
				//对于top这样的命令，运行后不会退出，新建一个临时进程。运行后需要手动退出
                $process = new swoole_process(array($this, 'onTmpProcess'), true, 2);
                $process->start();
                $this->async_process[$frame->fd] = $process;

                //读取子进程数据并发送
                swoole_event_add($process->pipe, function () use($process, $frame){
                    $data = $process->read();
                    var_dump($data);
                    $this->server->push($frame->fd, $data);
                });
            }
            $process->write($cmd);//执行shell命令
            sleep(1);
        }else{//一次性执行完毕的进程
            $this->process->write($cmd);//执行shell命令
            $data = $this->process->read();//获取执行结果
            $this->server->push($frame->fd, $data);
        }
    }

	//用于处理ls这样的命令，调用完自动退出
    public function onProcess(swoole_process $worker){
        while (true){
            $cmd = $worker->read();
            if($cmd == 'exit'){
                $worker->exit();
                break;
            }
            passthru($cmd);//执行外部程序并且显示未经处理的、原始输出，会直接echo。同 exec() 函数类似
            //echo exec($cmd);//exec只会输出命令执行结果的最后一行内容，且需要手动打印输出
			//echo '1111111111';//这里面直接输出会被捕获到
        }
    }

	//用于处理top这样的命令，调用完不会自动退出
    public function onTmpProcess(swoole_process $worker){
        $cmd = $worker->read();
        $handle = popen($cmd, 'r');

        swoole_event_add($worker->pipe, function () use($worker, $handle){
            $cmd = $worker->read();
            if($cmd == 'exit'){
                $worker->exit();
            }
            fwrite($handle, $cmd);
        });

        while (!feof($handle)){
            $buffer = fread($handle, 18192);
            echo $buffer;
        }
    }
	
	public function onClose($server, $fd){
		echo "client close: {$fd}\n";
	}
}

new Server();