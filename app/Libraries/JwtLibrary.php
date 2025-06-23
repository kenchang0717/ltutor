<?php

namespace App\Libraries;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
// use Config\Jwt;

class JwtLibrary
{
    protected $config;
    public string $key        = '4vy9lfed9DvMNPDFmc3j0EUg1YtOYvWd';
    public string $algorithm  = 'HS256';
    public int    $tokenTTL   = 3600*24; // token 有效时间（秒）

    public function __construct()
    {
    }

    public function generateToken(array $data): string
    {
        $payload = [
            'iss' => '',                            // 签发者
            'aud' => '',                            // 接收者
            'iat' => time(),                        // 签发时间
            'exp' => time() + $this->tokenTTL,
            'data' => $data
        ];

        return JWT::encode($payload, $this->key, $this->algorithm);
    }

    public function validateToken(string $token)
    {
        try {
            return JWT::decode($token, new Key($this->key, $this->algorithm));
        } catch (\Exception $e) {
            return false;
        }
    }
}
