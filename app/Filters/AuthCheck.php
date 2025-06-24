<?php
namespace App\Filters;

use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Filters\FilterInterface;
use App\Libraries\JwtLibrary;

class AuthCheck implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if(empty($request->getHeaderLine('authorization'))){
            return "請重新登入";
        }else{
            $new_str = str_replace("Bearer ","",$request->getHeaderLine('authorization'));
            $jwt = new JwtLibrary();
            $res = $jwt->validateToken($new_str);
            $userData = json_decode(json_encode($res), true);
            $GLOBALS['uid'] = $userData['data']['id'];
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // After filter 可選擇實作
    }
}
