<?php
namespace App\Controllers;

use App\Models\UserModel;
use App\Libraries\JwtLibrary;
use App\Libraries\RedisLibrary;

class User extends BaseController {
	public function login()
    {
        if(!isset($_REQUEST['account']) || $_REQUEST['account']=='')
            return "account is empty!";

        if(!isset($_REQUEST['password']) || $_REQUEST['password']=='')
            return "password is empty!";
        
    	$userModel = new UserModel();
        $where = [
            'email' => $_REQUEST['account'],
        ];
        $user = $userModel->where($where)->find();

        if(!password_verify($_REQUEST['password'], $user[0]['password'])){
            return "password is fail!";
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
