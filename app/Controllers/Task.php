<?php
namespace App\Controllers;

use App\Models\UserNotificationsModel;
use App\Models\UserChangeSchoolModel;
use App\Models\UserModel;
use App\Models\PointModel;
use App\Libraries\RedisLibrary;
use DateTime;
use DateTimeZone;
use CodeIgniter\I18n\Time;

class Task extends BaseController {
    private $tz;
    public function __construct() {
        $this->tz = new DateTimeZone('Asia/Taipei');
    }
    public function getBonusBySchoolLastWeek()
    {
        $date = $this->getLastWeekBetweenDate();

        $userchangeschoolModel = new UserChangeSchoolModel();
        $userList = $userchangeschoolModel->getUserChangeSchoolList($date['start'],$date['end']);
        $userListRes = json_decode($userList,true);
        if(count($userListRes)!=0)
            $userListdata=$userListRes;
        else
            $userListdata=array('0');

        $userModel = new UserModel();
        $data =$userModel->select('user_users.school_name,SUM(user_points_transactions.point) AS BONUS')
                    ->join('user_points_transactions', 'user_users.id = user_points_transactions.user_id', 'left')
                    ->where('user_points_transactions.operation','ADD')
                    ->where('user_points_transactions.transaction_type','TASK')
                    ->where('user_users.school_name !=','')
                    ->where("CONVERT_TZ(user_points_transactions.created_at, '+00:00', '+08:00') >=", $date['start'])
                    ->where("CONVERT_TZ(user_points_transactions.created_at, '+00:00', '+08:00') <=", $date['end'])
                    // ->where('user_points_transactions.created_at >=', $date['start'])
                    // ->where('user_points_transactions.created_at <=', $date['end'])
                    ->whereNotIn('user_points_transactions.user_id', $userListdata)
                    ->groupBy('user_users.school_name')
                    ->orderBy('BONUS','DESC')
                    ->limit(5)
                    ->findAll();

        $all['record'] = $data;
        $all['startTime'] = $date['start'];
        $all['endTime'] = $date['end'];

        $redis = new RedisLibrary();
        $redis->set('getBonusBySchoolLastWeek', json_encode($all),3600*24*7);

        return 'success';
    }

    public function getBonusBySchoolNow()
    {
        $now = Time::now($this->tz);

        // 先取得本週四中午 12:00
        $thisThursday = Time::parse('this thursday 12:00:00', $this->tz);

        // 如果現在時間早於本週四中午，代表週期是「上一個週四中午開始」
        if ($now->isBefore($thisThursday)) {
            $periodStart = Time::parse('last thursday 12:00:00', $this->tz);
        } else {
            // 否則就是本週四中午開始
            $periodStart = $thisThursday;
        }
        $start = $periodStart->toDateTimeString();

        // 週期結束 = 週期開始 + 6 天，再設定時間為 11:59:59
        $periodEnd = clone $periodStart;
        $periodEnd->addDays(6)->setTime(11, 59, 59);
        $end = $periodEnd->addDays(6)->setTime(11, 59, 59)->toDateTimeString();

        $userchangeschoolModel = new UserChangeSchoolModel();
        $userList = $userchangeschoolModel->getUserChangeSchoolList($start,$end);
        $userListRes = json_decode($userList,true);
        if(count($userListRes)!=0)
            $userListdata=$userListRes;
        else
            $userListdata=array('0');

        $userModel = new UserModel();
        $data =$userModel->select('user_users.school_name,SUM(user_points_transactions.point) AS BONUS')
                    ->join('user_points_transactions', 'user_users.id = user_points_transactions.user_id', 'left')
                    ->where('user_points_transactions.operation','ADD')
                    ->where('user_points_transactions.transaction_type','TASK')
                    ->where('user_users.school_name !=','')
                    ->where("CONVERT_TZ(user_points_transactions.created_at, '+00:00', '+08:00') >=", $start)
                    ->where("CONVERT_TZ(user_points_transactions.created_at, '+00:00', '+08:00') <=", $end)
                    // ->where('user_points_transactions.created_at >=', $start)
                    // ->where('user_points_transactions.created_at <=', $end)
                    ->whereNotIn('user_points_transactions.user_id', $userListdata)
                    ->groupBy('user_users.school_name')
                    ->orderBy('BONUS','DESC')
                    ->limit(5)
                    ->findAll();

        $all['record'] = $data;
        $all['startTime'] = $start;
        $all['endTime'] = $end;

        $redis = new RedisLibrary();
        $redis->set('getBonusBySchoolNow', json_encode($all),3600*24);
        return 'success';
    }

    public function getExtraBonusByUser()
    {
        $date = $this->getLastWeekBetweenDate();
        $pointModel = new PointModel();
        $data =$pointModel->select('user_id,SUM(point) AS BONUS')
                    ->where('operation','ADD')
                    ->where('transaction_type','TASK')
                    ->where("CONVERT_TZ(created_at, '+00:00', '+08:00') >=", $date['start'])
                    ->where("CONVERT_TZ(created_at, '+00:00', '+08:00') <=", $date['end'])
                    // ->where('created_at >=', $date['start'])
                    // ->where('created_at <=', $date['end'])
                    ->groupBy('user_id')
                    ->having('BONUS >=',500)
                    ->orderBy('BONUS','DESC')
                    ->limit(3)
                    ->findAll();

        $all['record'] = $data;
        $all['startTime'] = $date['start'];
        $all['endTime'] = $date['end'];

        $redis = new RedisLibrary();
        $redis->set('getExtraBonusByUser:'.date('Y-m-d',strtotime($date['start'])), json_encode($all),3600*24*30*2);

        $usernotificationsModel = new UserNotificationsModel();
        $notifications['title']='恭喜完成個人組任務挑戰，已成功領取獎勵紅利！';
        $notifications['content']='您好：
        恭喜您完成了個人組任務挑戰，並成功領取以下獎勵：
        紅利：[500]
        感謝您的積極參與和努力，期待您在未來持續挑戰更多任務！
        ';
        foreach($data as $k => $v){
            $this->getExtraBonus('user',$v['user_id'],500);
            $notifications['user_id']=$v['user_id'];
            $usernotificationsModel->add($notifications);
        }
        return 'success';
    }

    public function getExtraBonusBySchool()
    {
        $userModel = new UserModel();
        $list = $userModel->getSchoolList();

        $date = $this->getLastWeekBetweenDate();

        $userchangeschoolModel = new UserChangeSchoolModel();
        $userList = $userchangeschoolModel->getUserChangeSchoolList($date['start'],$date['end']);
        $userListRes = json_decode($userList,true);
        if(count($userListRes)!=0)
            $userListdata=$userListRes;
        else
            $userListdata=array('0');

        
        foreach($list as $k => $v){
            $data =$userModel->select('user_points_transactions.user_id,SUM(user_points_transactions.point) AS BONUS')
                    ->join('user_points_transactions', 'user_users.id = user_points_transactions.user_id', 'left')
                    ->where('user_points_transactions.operation','ADD')
                    ->where('user_points_transactions.transaction_type','TASK')
                    ->where('user_users.school_name',$v['school_name'])
                    ->where("CONVERT_TZ(user_points_transactions.created_at, '+00:00', '+08:00') >=", $date['start'])
                    ->where("CONVERT_TZ(user_points_transactions.created_at, '+00:00', '+08:00') <=", $date['end'])
                    // ->where('user_points_transactions.created_at >=', $date['start'])
                    // ->where('user_points_transactions.created_at <=', $date['end'])
                    ->whereNotIn('user_points_transactions.user_id', $userListdata)
                    ->groupBy('user_points_transactions.user_id')
                    ->orderBy('BONUS','DESC')
                    ->limit(3)
                    ->findAll();

            $all['record'] = $data;
            $all['startTime'] = $date['start'];
            $all['endTime'] = $date['end'];

            $redis = new RedisLibrary();
            $redis->set('getExtraBonusBySchool'.$v['school_name'].':'.date('Y-m-d',strtotime($date['start'])), json_encode($all),3600*24*30*2);

            foreach($data as $k => $v){
                if($k==0){
                    $this->getExtraBonus('school',$v['user_id'],1000);
                    $notifications['content']='您好：
                    恭喜您完成了校際組任務挑戰，並成功領取以下獎勵：
                    紅利：[1000]
                    感謝您的積極參與和努力，期待您在未來持續挑戰更多任務！
                    '; 
                }elseif($k==1){
                    $this->getExtraBonus('school',$v['user_id'],500);
                    $notifications['content']='您好：
                    恭喜您完成了校際組任務挑戰，並成功領取以下獎勵：
                    紅利：[500]
                    感謝您的積極參與和努力，期待您在未來持續挑戰更多任務！
                    '; 
                }elseif($k==2){
                    $this->getExtraBonus('school',$v['user_id'],100);
                    $notifications['content']='您好：
                    恭喜您完成了校際組任務挑戰，並成功領取以下獎勵：
                    紅利：[100]
                    感謝您的積極參與和努力，期待您在未來持續挑戰更多任務！
                    '; 
                }else
                    continue;

                $usernotificationsModel = new UserNotificationsModel();
                $notifications['title']='恭喜完成校際組任務挑戰，已成功領取獎勵紅利！';
                $notifications['user_id']=$v['user_id'];
                $usernotificationsModel->add($notifications);
            }
        }
        return 'success';
    }

    public function getExtraBonus(string $type,int $uid,int $bonus)
    {
        $userModel = new UserModel();
        $pointModel = new PointModel();
        $info = $userModel->getUserInfo($uid);

        $res = $userModel->updateBonus($uid,$bonus,$info['bonus_points']);
        if($res == 'success'){
            $pointModel->addBonusLog($uid,$bonus,$type,$info['bonus_points']);
            return 'success';
        }else{
            return 'false';
        }
    }

    public function getLastWeekBetweenDate(){
        $now = Time::now($this->tz);
        // 本週四中午
        $thisThursday = Time::parse('this thursday 12:00:00', $this->tz);

        // 如果今天還沒到週四中午，表示我們還在「上週」區間中
        if ($now->isBefore($thisThursday)) {
            $thisThursday = Time::parse('last thursday 12:00:00', $this->tz);
        }

        // 上週四 = 本週四 - 7 天
        $start = Time::parse($thisThursday->toDateTimeString(),$this->tz)->subDays(7)->toDateTimeString();

        // 本週三中午 11:59:59 = 本週四 - 1 天 + 設定時間
        $end = Time::parse($thisThursday->toDateTimeString(), $this->tz)->subDays(1)->setTime(11, 59, 59)->toDateTimeString();

        $date['start'] = $start;
        $date['end'] = $end;

        return $date;
    }

    public function test(){
        $now = Time::now($this->tz);

        $redis = new RedisLibrary();
        $redis->set('CRON_WORK_'.$now ,'',3600*24);

        return 'success';
    }

}
