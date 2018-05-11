<?php 

    /**
     * PHP实现的超轻量级SOCKET CLIENT端连接池 实现非阻塞并发套接字
     * 通过多个套接字非阻塞的执行监听
     * @Auther QiuXiangCheng
     * @Date   2018/05/11
     */
    class concurrencySocket {

        // 套接字句柄
        private $socket;

        // 监听的端口
        private $port = '3100';

        // 地址
        private $host = '127.0.0.1';

        // 建立连接超时时间
        private $timeout = 3;

        // 一次性创建多个监听
        private $socketsAmount = 100;

        // 要发送的信息
        private $socket_msg;

        // 简易连接池
        private $socket_streams = [];

        // 初始化的同时创建套接字
        public function __construct() {

            $this -> init();
        }

        /**
         * 批量创建套接字
         * 为了防止fwrite中的socket句柄创建时间 每次创建都应该做一些廷时
         * 这是一个PHP的BUG
         */
        private function init() {

            for ($i = count($this -> socket_streams); $i < $this -> socketsAmount; $i ++) {
                usleep(30);
                $this -> addSocket($i);
            }
        }

        // 增加一个SOCKET连接
        private function addSocket($i) {

            $this -> socket_streams[$i] = stream_socket_client($this -> host . ':' . $this -> port,
                $errno, $errstr,
                $this -> timeout,
                STREAM_CLIENT_ASYNC_CONNECT|STREAM_CLIENT_CONNECT
            );
        }

        // 随机找到一个套接字Stream发送数据
        public function sendMessage($msg) {

            $streamsAmount = count($this -> socket_streams);
            if ($streamsAmount == 0) {
                $this -> init();
                $this -> sendMessage($msg);
                return;
            }

            // 为了附上粘包 加上一个非线程安全的延迟时间
            usleep(100);

            // 随机取一个连接编号进行发送
            $randSid = rand(0, $streamsAmount - 1);
            echo $randSid . "\n";

            // 通过@符号忽略因TCP服务器断开等因素导致的fwrite异常
            // 因为try catch无法捕获到fwrite的异常
            $fwrite = @fwrite($this -> socket_streams[$randSid], "www" . $randSid . $msg);
            if (!$fwrite) {
                echo "连接失败，删除这个编号的套接字并重试...\n";
                fclose($this -> socket_streams[$randSid]);
                $this -> delUnSocket($randSid);
                $this -> addSocket(count($this -> socket_streams));
                $this -> sendMessage($msg);
            }
        }

        // 删除一个SOCKET连接
        private function delUnSocket($i) {

            unset($this -> socket_streams[$i]);
            $this -> socket_streams = array_values($this -> socket_streams);
        }
    }

    $scs = new concurrencySocket;

    // 每秒发送30万条信息
    // 测试10分钟 未出现一次粘包
    while(1) {

        for ($i = 0; $i < 300000; $i ++) {
            $scs -> sendMessage($i);
        }
        sleep(1);
    }
