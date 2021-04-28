<?php
require_once('vendor/autoload.php');
require_once('Time.php');

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
    new React\Http\Middleware\StreamingRequestMiddleware(),
    function (Psr\Http\Message\ServerRequestInterface $request) use($loop, $redis) {
    $path = $request->getUri()->getPath();

    switch ($path){
        case '/time/get':
            $Time = Time::getInstance();
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
            return new React\Promise\Promise(function ($resolve) use ($body) {
                $bytes = 0;
                $body->on('data', function ($chunk) use (&$bytes) {
                    $bytes += strlen($chunk);
                    echo "step 1 : $bytes \n";
                });
                $body->on('end', function () use (&$bytes, $resolve) {
                    echo "step 2 : $bytes \n";
                    $resolve(new React\Http\Message\Response(
                        200,
                        ['Content-Type' => 'text/plain'],
                        "Bytes: $bytes\n"
                    ));
                });
            });
    }
});

$socket = new React\Socket\Server(8081, $loop);
$server->listen($socket);

echo "Server running !\n";

$loop->run();


