<?php
require_once('vendor/autoload.php');

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

$rabbitmq = new AMQPStreamConnection('localhost', 5672, 'guest', 'guest');
$channel = $rabbitmq->channel();
$channel->queue_declare('message', false, false, false, false);

echo "Waiting message... \n";

$channel->basic_consume('message', '', false, true, false, false, function ($msg){
    sleep(5);
    echo "New message: ".$msg->body." \n";
});

while ($channel->is_open()) {
    $channel->wait();
}

$channel->close();
$connection->close();




