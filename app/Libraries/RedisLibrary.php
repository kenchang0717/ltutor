<?php

namespace App\Libraries;

use Predis\Client;

class RedisLibrary
{
    protected $redis;
    public $ip = '127.0.0.1';
    public $port = 6379;

    public function __construct()
    {
        if (ENVIRONMENT === 'production') {
            $this->ip   = '127.0.0.1';
            $this->port = 6379;
        }

        $this->redis = new Client([
            'scheme' => 'tcp',
            'host'   => $this->ip,
            'port'   => $this->port,
            // 'password' => 'yourpassword' // 如果有设置密码
        ]);
    }

    public function set($key, $value, $expire = 0)
    {
        $this->redis->set($key, $value);
        if ($expire > 0) {
            $this->redis->expire($key, $expire);
        }
    }

    public function get($key)
    {
        return $this->redis->get($key);
    }

    public function delete($key)
    {
        return $this->redis->del([$key]);
    }
}
