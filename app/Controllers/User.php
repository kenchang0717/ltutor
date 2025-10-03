<?php
namespace App\Controllers;

use App\Models\PointModel;
use App\Models\UserModel;
use App\Models\UserPsychologicalModel;
use App\Models\UserNotificationsModel;
use App\Libraries\JwtLibrary;
use App\Libraries\RedisLibrary;
use PhpOffice\PhpSpreadsheet\IOFactory;

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

    public function sendMessage()
    {
        $data = explode(",", $_REQUEST['ids']);
        $notifications['title']='【叫我註冊王】活動通知';
        if($_REQUEST['content']==1){
            $notifications['content']='叮咚🔔

                最近有一些同學手上的推薦碼還留著沒用～

                如果再多邀請幾位註冊，成績就能更完整，名次也會更往前啦！

                👉 一起加油，把手上的推薦碼都用起來，說不定能衝出更亮眼的表現喔！

                對了，第一次段考也將至，提醒如果遇到不懂的題目可以問 S.E.N.S.E.I、想要刷題可以使用 Qbot 喔！

        ';
        }else if($_REQUEST['content']==2){
            $notifications['content']='叮咚🔔
            
            好多同學們已經完成第一組邀約註冊，真的很棒👏是一個好的開始呢！

            接下來如果再多邀請幾位，成果會更加驚喜，前五名邀約還有高額獎學金喔！
            
            提醒同學們，本活動最終僅取前 10 名。若出現相同邀約人數，將依「最早完成邀約註冊時間」排序，優先者獲得名次喔。

            👉 保持這股動力，一起往前衝，加油！

        ';
        }else if($_REQUEST['content']==3){
            $notifications['content']='叮咚🔔

            有些同學買了一組推薦碼，但還沒開始邀請～

            已經很不錯，有好的開始，只是有點可惜，其實只要先邀請一位，後面就會慢慢累積成果哦，紅利與獎學金等你來拿！

            如果還不知道怎麼邀約，歡迎到龍騰高中聲 IG 精選動態第 4 則看邀約流程喔：https://www.instagram.com/stories/highlights/18078465647000530/ 

            如果還是不懂，歡迎私訊龍騰高中聲 Line@ 或是龍騰高中聲 IG 詢問小編喔！

            👉 別擔心，第一步最重要，一起加油，踏出去就對了！

            對了，第一次段考也將至，提醒如果遇到不懂的題目可以問 S.E.N.S.E.I、想要刷題可以使用 Qbot 喔！

        ';
        }
        
        $notifications['name']='register_king_activity';
        $usernotificationsModel = new UserNotificationsModel();

        foreach($data as $k => $v){
            $notifications['user_id']=$v;
            $usernotificationsModel->add($notifications);
        }
        return 'success';
    }

    public function readExcel()
    {
        $file = $this->request->getFile('excel');

        if (!$file->isValid()) {
            return $this->response->setJSON(['success' => false, 'message' => '檔案無效']);
        }

        // 讀取 Excel 檔案
        $spreadsheet = IOFactory::load($file->getTempName());
        $sheet = $spreadsheet->getActiveSheet();
        $data = $sheet->toArray(); // 轉成陣列格式

        $userModel = new UserModel();
        $userPsychologicalModel = new UserPsychologicalModel();
        $usernotificationsModel = new UserNotificationsModel();
        foreach($data as $k => $v){    
            if ($k === 0) continue;

            // 確保 Email 存在
            if (!isset($v[2]) || !filter_var($v[2], FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            $res = $userPsychologicalModel->checkEmailExist($v[2]);
            if($res!=null){
                $info = $userModel->getUserInfoByEmail($res);
            if($info != 0){
                $userPsychologicalModel->add($info['id'],$v[2],1);
                $pointsRes = $userModel->updateBonus($info['id'],3000,$info['bonus_points']);
                if($pointsRes == 'success'){
                    $notifications['title']='心理測驗活動獎勵';
                    $notifications['content']='親愛的同學 ，您好：

                    感謝您參加本次 LTrust 所推出的「你是哪種學習型人格」心理測驗活動！

                    您已完成 email 登記，我們已為您發送 3000 點紅利至帳戶中。

                    紅利可用於兌換 LTrust 上的各項學習服務，目前 S.E.N.S.E.I 解題教練問到飽 正在進行中，同學不要害羞，免費期間盡量用起來！

                    此外，平台也同步舉辦「紅利提款機挑戰賽」，可以再LTrust首頁BANNER上找到「Lucky7 紅利提款機大賽」的活動喔！天天完成任務還能額外賺紅利，快來看看吧💰

                    ';
                    $notifications['user_id']=$info['id'];
                    $usernotificationsModel->add($notifications);
                    }              
                } 
                else{
                    $userPsychologicalModel->add(0,$v[2],0);
                }
            }  
        }
        return $this->response->setJSON(['success' => true]);
    }

        public function readExcelRegister()
    {
        $file = $this->request->getFile('excel');

        if (!$file->isValid()) {
            return $this->response->setJSON(['success' => false, 'message' => '檔案無效']);
        }

        // 讀取 Excel 檔案
        $spreadsheet = IOFactory::load($file->getTempName());
        $sheet = $spreadsheet->getActiveSheet();
        $data = $sheet->toArray(); // 轉成陣列格式

        $userModel = new UserModel();
        $userPsychologicalModel = new UserPsychologicalModel();
        $usernotificationsModel = new UserNotificationsModel();
        foreach($data as $k => $v){
            if ($k === 0) continue;

            // 確保 Email 存在
            if (!isset($v[2]) || !filter_var($v[2], FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            $res = $userPsychologicalModel->checkEmailExist($v[2]);
            if($res!=null){
                $info = $userModel->getUserInfoByEmail($res);
            if($info != 0){
                $userPsychologicalModel->add($info['id'],$v[2],1);
                $pointsRes = $userModel->updateBonus($info['id'],100,$info['bonus_points']);
                if($pointsRes == 'success'){
                    $notifications['title']='叫我註冊王_2_email活動獎勵';
                    $notifications['content']='親愛的同學 ，您好：

                    叮咚～龍騰高中聲 LINE 推播好禮來囉！🎉

                    恭喜同學獲得 100 紅利！

                    這 100 紅利可用於購買「叫我註冊王」活動推薦碼，邀請同學一起註冊 LTrust！邀請越多朋友註冊完成，就有機會獲得最高 新台幣 3,000 元獎金。天大好機會不要錯過啦！

                    想知道更多「叫我註冊王」活動資訊 👉 https://cmrk.ltrust.tw/

                    ';
                    $notifications['user_id']=$info['id'];
                    $usernotificationsModel->add($notifications);
                    }              
                } 
                else{
                    $userPsychologicalModel->add(0,$v[2],0);
                }
            }
        }
        return $this->response->setJSON(['success' => true]);
    }

    public function supplyLog()
    {
        
        $userPsychologicalModel = new UserPsychologicalModel();
        $res = $userPsychologicalModel->getLog('2025-10-01');

        $userModel = new UserModel();
        $pointModel = new PointModel();
        foreach($res as $k => $v){
            $info = $userModel->getUserInfo($v['uid']);
            $before = $info['bonus_points']-100;
            $pointModel->addRegisterBonusLog($v['uid'],100,$before);
        }
        return 'success';
    }
}
