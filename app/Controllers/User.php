<?php
namespace App\Controllers;

use App\Models\UserModel;
use App\Libraries\JwtLibrary;
use App\Libraries\RedisLibrary;

class User extends BaseController {
	public function login()
    {
        // 獲取 JSON 請求數據
        $json = $this->request->getJSON(true); // true 表示返回關聯數組

        if(!isset($json['account']) || $json['account']==''){
            $data = [
            'status'  => false,
            'data'  => '',
            'message' => '帳號為空,請重新登入!'
            ];
            return $this->response->setJSON($data);
        }

        if(!isset($json['password']) || $json['password']==''){
            $data = [
            'status'  => false,
            'data'  => '',
            'message' => '密碼為空,請重新登入!'
            ];
            return $this->response->setJSON($data);
        }
        
    	$userModel = new UserModel();
        $where = [
            'email' => $json['account'],
        ];
        $user = $userModel->where($where)->find();

        if(!password_verify($json['password'], $user[0]['password'])){
            $data = [
            'status'  => false,
            'data'  => '',
            'message' => '密碼錯誤,請重新登入!'
            ];
            return $this->response->setJSON($data);
        }

        if(!$user[0]['is_verified']){
            $data = [
            'status'  => false,
            'data'  => '',
            'message' => '帳號未驗證,請先驗證信箱!'
            ];
            return $this->response->setJSON($data);
        }
            

        $jwt = new JwtLibrary();
        $tokenData = [
            'id' => $user[0]['id'],
            'email' => $user[0]['email'],
            'name' => $user[0]['name'],
        ];
        $token = $jwt->generateToken($tokenData);

        $data = [
        'status'  => true,
        'data'  => ['token' => $token,'uid' => $user[0]['id'],'name' => $user[0]['name']],
        'message' => 'success'
        ];

        $redis = new RedisLibrary();
        $redis->set('userToken:'.$user[0]['id'], $token,3600*24);

        return $this->response->setJSON($data);
    }

    public function logout()
    {
        $data = [
        'status'  => true,
        'data'  => '',
        'message' => 'success'
        ];

        $redis = new RedisLibrary();
        $redis->delete('userToken:'.$GLOBALS['uid']);

        return $this->response->setJSON($data);
    }

    public function getSchoolList()
    {
        $userModel = new UserModel();
        $list = $userModel->getSchoolList();
        $data = [
        'status'  => true,
        'data'  => $list,
        'message' => 'success'
        ];
        return $this->response->setJSON($data);
    }
}
