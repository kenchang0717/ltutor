<?php

namespace App\Libraries;

use Predis\Client;

class RedisLibrary
{
    protected $redis;
    public function __construct()
    {
        $this->redis = new Client([
            'scheme' => 'tcp',
            'host'   => '127.0.0.1',
            'port'   => 6379,
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
