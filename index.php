<?php
require_once('vendor/autoload.php');
require_once('Time.php');

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

$rabbitmq = new AMQPStreamConnection('localhost', 5672, 'guest', 'guest');

$redis = new Predis\Client([
    'scheme' => 'tcp',
    'host'   => '127.0.0.1',
    'port'   => 6379,
]);

$loop = React\EventLoop\Factory::create();

$loop->addPeriodicTimer(20, function () {
    $Time = Time::getInstance();
    $Time->updateTime();
    echo "Set time: ".$Time->getTime()."\n";
});



$server = new React\Http\Server(
    $loop,
//    new React\Http\Middleware\StreamingRequestMiddleware(),
    function (Psr\Http\Message\ServerRequestInterface $request) use($loop, $redis, $rabbitmq) {
    $path = $request->getUri()->getPath();

    switch ($path){
        case '/message':
            $data = json_decode($request->getBody());

            $channel = $rabbitmq->channel();
            $channel->queue_declare('message', false, false, false, false);
            for ($i=1; $i <= 15; $i++){
                $msg = new AMQPMessage($data->text." $i");
                $channel->basic_publish($msg, '', 'message');
            }
            $channel->close();

            echo "Message sent successfully! \n";

            return new React\Http\Message\Response(
                200,
                array(
                    'Content-Type' => 'application/json'
                ),
                "Message sent successfully! \n"
            );
        case '/time/get':
            return new React\Http\Message\Response(
                200,
                array(
                    'Content-Type' => 'text/plain'
                ),
                $redis->get('time')."\n"
            );
        case '/time/update':
            $Time = Time::getInstance();
            $redis->set('time', $Time->getTime());
            $redis->expire('time', 120);
            return new React\Http\Message\Response(
                200,
                array(
                    'Content-Type' => 'text/plain'
                ),
                $Time->getTime()."\n"
            );
        case '/time':
            $Time = Time::getInstance();
            return new React\Http\Message\Response(
                200,
                array(
                    'Content-Type' => 'text/plain'
                ),
                $Time->getTime()."\n"
            );
        case '/prices':
            $data = json_decode($request->getBody());
            $discount = 20; // 20%
            foreach ($data->products as $prod){
                $prod->Discount = round($prod->Price - (($prod->Price * $discount) / 100), 2);
            }
            return new React\Http\Message\Response(
                200,
                array(
                    'Content-Type' => 'application/json'
                ),
                json_encode($data)
            );
        case '/upload':
            $files = $request->getUploadedFiles();
            /** @var \Psr\Http\Message\UploadedFileInterface|null $file */
            $file = $files['file'];
            $filename = $file->getClientFilename();

            if($file) {
                $dest = new \React\Stream\WritableResourceStream(fopen('uploads/'.$filename, 'w'), $loop);
                $dest->write($file->getStream());
            }
            return new React\Http\Message\Response(
                200,
                array(
                    'Content-Type' => 'text/plain'
                ),
                "Upload successful:  $filename \n"
            );
        case '/uploadfile':
            $body = $request->getBody();
            /** @var \React\Stream\ReadableStreamInterface|null $body */

            $stream = new \React\Stream\ReadableResourceStream(STDIN, $loop);
            $stream->on('data', function ($chunk) {
                echo $chunk;
            });

            return new React\Promise\Promise(function ($resolve) use ($body, $loop) {
                $bytes = 0;
                $data = '';
                $body->on('data', function ($chunk) use (&$bytes, &$data) {
                    $bytes += strlen($chunk);
                    $data .= $chunk;
//                    echo "step 1 : $bytes \n";
                });
                $body->on('end', function () use (&$bytes, &$data, $resolve, $loop) {
//                    $stdout = new \React\Stream\WritableResourceStream(STDOUT, $loop);
//                    $stdout = new \React\Stream\WritableResourceStream(fopen('uploads/test.txt', 'w'), $loop);
//                    $stdin = new \React\Stream\ReadableResourceStream(STDIN, $loop);
//                    $stdin->pipe($stdout);


//                    $output = new \React\Stream\WritableResourceStream(fopen('uploads/test.txt', 'w'), $loop);
//                    $output = new \React\Stream\WritableResourceStream(fopen('uploads/img.png', 'w'), $loop);
//                    $output->write(base64_decode($data));

//                    $filesystem = \React\Filesystem\Filesystem::create($loop);
//                    $filesystem->file('uploads/test2.txt')->putContents($data);

                    //echo "step 2 : $bytes \n";
                    $resolve(new React\Http\Message\Response(
                        200,
                        ['Content-Type' => 'text/plain'],
                        "Bytes: $bytes\n"
                    ));
                });
            });

//            $output = new \React\Stream\WritableResourceStream(fopen('uploads/test.txt', 'w'), $loop);
//            $body->on('data', function ($data) use ($output){
//                $output->write($data);
//            });
    }
});

$socket = new React\Socket\Server(8081, $loop);
$server->listen($socket);

echo "Server running !\n";

$loop->run();


