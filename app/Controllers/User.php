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

        // $redis = new RedisLibrary();
        // $redis->set('userToken:'.$user[0]['id'], $token,3600*24);

        return $this->response->setJSON($data);
    }

    public function getToken()
    {
        // $data = [
        //     'vendorClientId' => $_POST['vendorClientId'],
        //     'userToken' => $_POST['userToken'],
        // ];

        $data = $this->getUserInfo($_POST['userToken']);
        $userData = json_decode($data, true);

        $userModel = new UserModel();
        $uid = $userModel->getUid($userData['data']['email']);

        $jwt = new JwtLibrary();
        $tokenData = [
            'id' => $uid,
            'email' => $userData['data']['email'],
            'name' => $userData['data']['name'],
        ];
        $token = $jwt->generateToken($tokenData);

        $url = "https://25bta.ltrust.tw/?uid=".$uid."&token=$token";

        header("Refresh: 3; url=$url");
        exit;
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

    public function getUserInfo(string $userToken)
    {
        $apiUrl = 'https://vendor.ltrust.tw/api/vendor/user/info';  
        $clientId = '4a4da231-c514-47d2-93f6-7be70c770a84';  
        $key = '65f8591f2edb818cb67b3b31713d6e16';            
        $token = $userToken;            

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $apiUrl);             
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "x-client-id: $clientId",
            "x-apikey: $key",
            "x-user-token: $token",
        ]);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            // echo 'cURL 錯誤: ' . curl_error($ch);

            return curl_error($ch);
        } else {
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            // echo "HTTP 狀態碼: $httpCode\n";
            // echo "回應內容:\n$response";

            return $response;
        }

        curl_close($ch);
    }
}
