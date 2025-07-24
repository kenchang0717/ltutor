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
        $data =$userModel->select('user_users.school_name,SUM(user_points_transactions.points) AS BONUS')
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
        $data =$userModel->select('user_users.school_name,SUM(user_points_transactions.points) AS BONUS')
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
        $Bdate = $this->getLastWeekBonusDate();

        $pointModel = new PointModel();
        $redis = new RedisLibrary();
        foreach($Bdate as $k => $v){
            if(date('Y-m-d H:i:s')<=$v['start']){
                // $ExistData = $pointModel->select('*')
                //         ->where('operation','ADD')
                //         ->where('transaction_type','SYSTEM')
                //         ->where('description','個人任務獎勵')
                //         ->where("CONVERT_TZ(created_at, '+00:00', '+08:00') >=", $Bdate[$k-1]['start'])
                //         ->where("CONVERT_TZ(created_at, '+00:00', '+08:00') <=", $Bdate[$k-1]['end'])
                //         ->findAll();
                $ExistData = $redis->get('getExtraBonusByUser:'.date('Y-m-d',strtotime($Bdate[$k-2]['start'])));
                if(!empty($ExistData))
                    return 'already push bonus';
                break;        
            }
        }

        $data = $pointModel->select('user_id,SUM(points) AS BONUS')
                    ->where('operation','ADD')
                    ->where('transaction_type','TASK')
                    ->where("CONVERT_TZ(created_at, '+00:00', '+08:00') >=", $date['start'])
                    ->where("CONVERT_TZ(created_at, '+00:00', '+08:00') <=", $date['end'])
                    // ->where('created_at >=', $date['start'])
                    // ->where('created_at <=', $date['end'])
                    ->groupBy('user_id')
                    ->having('BONUS >=',500)
                    ->orderBy('BONUS','DESC')
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
        $redis = new RedisLibrary();
        $record = $redis->get('getBonusBySchoolLastWeek');
        if(empty($record))
            return 'no data';

        $Bdate = $this->getLastWeekBonusDate();    
        $pointModel = new PointModel();
        foreach($Bdate as $k => $v){
            if(date('Y-m-d H:i:s')<=$v['start']){
                // $ExistData = $pointModel->select('*')
                //         ->where('operation','ADD')
                //         ->where('transaction_type','SYSTEM')
                //         ->where('description','學校任務獎勵')
                //         ->where("CONVERT_TZ(created_at, '+00:00', '+08:00') >=", $Bdate[$k-1]['start'])
                //         ->where("CONVERT_TZ(created_at, '+00:00', '+08:00') <=", $Bdate[$k-1]['end'])
                //         ->findAll();
                $ExistData = $redis->get('getExtraBonusBySchool:'.date('Y-m-d',strtotime($Bdate[$k-2]['start'])));
                if(!empty($ExistData))
                    return 'already push bonus';
                break;        
            }
        }

        $data = json_decode($record , true);

        $redis = new RedisLibrary();
        $redis->set('getExtraBonusBySchool:'.date('Y-m-d',strtotime($data['startTime'])), json_encode($data['record']),3600*24*30*2);

        $num=0;
        $usernotificationsModel = new UserNotificationsModel();
        $userlModel = new UserModel();
        $userchangeschoolModel = new UserChangeSchoolModel();
        foreach($data['record'] as $k => $v){
            $userList = $userlModel->getUidBySchool($v['school_name']);
            $NotQualifiedList = $userchangeschoolModel->getUserChangeSchoolList($data['startTime'],$data['endTime']);
            $NotQualifiedRes = json_decode($NotQualifiedList,true);
            $finalUserList = array_diff($userList, $NotQualifiedRes);

            foreach($finalUserList as $uk => $uv){
                if($num==0){
                $this->getExtraBonus('school',$uv,1000);
                $notifications['content']='您好：
                恭喜您完成了校際組任務挑戰，並成功領取以下獎勵：
                紅利：[1000]
                感謝您的積極參與和努力，期待您在未來持續挑戰更多任務！
                '; 
            }elseif($num==1){
                $this->getExtraBonus('school',$uv,500);
                $notifications['content']='您好：
                恭喜您完成了校際組任務挑戰，並成功領取以下獎勵：
                紅利：[500]
                感謝您的積極參與和努力，期待您在未來持續挑戰更多任務！
                ';
            }elseif($num==2){
                $this->getExtraBonus('school',$uv,100);
                $notifications['content']='您好：
                恭喜您完成了校際組任務挑戰，並成功領取以下獎勵：
                紅利：[100]
                感謝您的積極參與和努力，期待您在未來持續挑戰更多任務！
                '; 
            }
            
            $notifications['title']='恭喜完成校際組任務挑戰，已成功領取獎勵紅利！';
            $notifications['user_id']=$uv;
            $usernotificationsModel->add($notifications);
            }
            $num++;
            if($num>=3)
                break;
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

    public function getLastWeekBonusDate()
    {
        $weeks = [];

            $customStartDates = [
                1 => '2025-07-10 12:00:00',
                2 => '2025-07-17 12:00:00',
                3 => '2025-07-24 12:00:00',
                4 => '2025-07-31 12:00:00',
                5 => '2025-08-07 12:00:00',
                6 => '2025-08-14 12:00:00',
                7 => '2025-08-21 12:00:00',
                8 => '2025-08-27 12:00:00', 
            ];

            foreach ($customStartDates as $weekNo => $startStr) {
                    $start = Time::parse($startStr, $this->tz);
                    $end = Time::parse($start->toDateTimeString(), $this->tz)->addDays(7)->setTime(11, 59, 59);

                    $weeks[] = [
                        // 'week'     => $weekNo,
                        // 'start'    => $start,
                        // 'end'      => $end,
                        'start' => $start->toDateTimeString(),
                        'end'   => $end->toDateTimeString(),
                    ];
                }

        return $weeks;
    }

    public function test(){
        $now = Time::now($this->tz);

        $redis = new RedisLibrary();
        $redis->set('CRON_WORK_'.$now ,'',3600*24);

        return 'success';
    }
}
