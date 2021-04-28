<?php


class Time{
    private static $instance = null;
    private $time;

    private function __construct(){}

    public static function getInstance(){
        if (static::$instance === null) {
            static::$instance = new Time();
            static::$instance->updateTime();
        }
        return static::$instance;
    }

    public function updateTime(){
        $this->time = date("Y-m-d H:i:s");
    }

    public function getTime(){
        return $this->time;
    }

    protected function __clone() {}
    protected function __wakeup() {}
}