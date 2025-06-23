<?php
namespace App\Filters;

use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Filters\FilterInterface;

class AuthCheck implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        // 檢查使用者是否登入（假設用 session 判斷）
        // if (!$_SESSION['isLoggedIn']) {
        //     return '請先登入!!';
        // }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // After filter 可選擇實作
    }
}
