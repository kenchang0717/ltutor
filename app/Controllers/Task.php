<?php
namespace App\Controllers;

use App\Models\UserModel;
use App\Models\PointModel;
use App\Libraries\RedisLibrary;
use DateTime;

class Task extends BaseController {
    public function getBonusBySchoolLastWeek()
    {
        // 現在時間
        $now = time();
        // 計算本週三中午（這週區間的結尾）
        $thisWednesdayNoon = strtotime('this wednesday 12:00');
        // 如果現在還沒到本週三中午 → 本週三是未來，取上上週為起點
        if ($now < $thisWednesdayNoon) {
            $thisWednesdayNoon = strtotime('last wednesday 12:00');
        }
        // 區間結束：本週三中午 - 1 秒
        $end = $thisWednesdayNoon - 1;
        // 區間起始：本週三中午 - 7 天
        $start = strtotime('-7 days', $thisWednesdayNoon);
        // 格式化成 MySQL datetime 格式
        $startStr = date('Y-m-d H:i:s', $start);
        $endStr   = date('Y-m-d H:i:s', $end);

        $userModel = new UserModel();
        $data =$userModel->select('user_users.school_name,SUM(user_points_transactions.point_balance) AS BONUS')
                    ->join('user_points_transactions', 'user_users.id = user_points_transactions.user_id', 'left')
                    ->where('user_points_transactions.operation','ADD')
                    ->where('user_users.school_name !=','')
                    ->where('user_points_transactions.created_at >=', $startStr)
                    ->where('user_points_transactions.created_at <=', $endStr)
                    ->groupBy('user_users.school_name')
                    ->orderBy('BONUS','DESC')
                    ->limit(5)
                    ->findAll();

        $all['record'] = $data;
        $all['startTime'] = $startStr;
        $all['endTime'] = $endStr;

        $redis = new RedisLibrary();
        $redis->set('getBonusBySchoolLastWeek', json_encode($all),3600*24*7);
        return 'success';
    }

    public function getBonusBySchoolNow()
    {
        $now = time();
        // 本週三中午的時間點
        $thisWednesdayNoon = strtotime('this wednesday 12:00');
        // 如果現在時間早於本週三中午，則使用「上週三中午」作為起點
        if ($now < $thisWednesdayNoon) {
            $start = strtotime('last wednesday 12:00');
        } else {
            $start = $thisWednesdayNoon;
        }
        // 區間結束為「起點 + 7 天 - 1 秒」
        $end = strtotime('+7 days -1 second', $start);
        // 格式化為 MySQL DATETIME 格式
        $startStr = date('Y-m-d H:i:s', $start);
        $endStr = date('Y-m-d H:i:s', $end);

        $userModel = new UserModel();
        $data =$userModel->select('user_users.school_name,SUM(user_points_transactions.point_balance) AS BONUS')
                    ->join('user_points_transactions', 'user_users.id = user_points_transactions.user_id', 'left')
                    ->where('user_points_transactions.operation','ADD')
                    ->where('user_users.school_name !=','')
                    ->where('user_points_transactions.created_at >=', $startStr)
                    ->where('user_points_transactions.created_at <=', $endStr)
                    ->groupBy('user_users.school_name')
                    ->orderBy('BONUS','DESC')
                    ->limit(5)
                    ->findAll();

        $all['record'] = $data;
        $all['startTime'] = $startStr;
        $all['endTime'] = $endStr;

        $redis = new RedisLibrary();
        $redis->set('getBonusBySchoolNow', json_encode($all),3600*24);
        return 'success';
    }

    public function getExtraBonusByUser()
    {
        $date = $this->getLastWeekBetweenDate();
        $pointModel = new PointModel();
        $data =$pointModel->select('user_id,SUM(point_balance) AS BONUS')
                    ->where('operation','ADD')
                    ->where('created_at >=', $date['start'])
                    ->where('created_at <=', $date['end'])
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
        foreach($data as $k => $v){
            $this->getExtraBonus('user',$v['user_id'],500);
        }
        
        return 'success';
    }

    public function getExtraBonusBySchool()
    {
        $userModel = new UserModel();
        $list = $userModel->getSchoolList();

        $date = $this->getLastWeekBetweenDate();
        
        foreach($list as $k => $v){
            $data =$userModel->select('user_points_transactions.user_id,SUM(user_points_transactions.point_balance) AS BONUS')
                    ->join('user_points_transactions', 'user_users.id = user_points_transactions.user_id', 'left')
                    ->where('user_points_transactions.operation','ADD')
                    ->where('user_users.school_name',$v['school_name'])
                    ->where('user_points_transactions.created_at >=', $date['start'])
                    ->where('user_points_transactions.created_at <=', $date['end'])
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
                if($k==0)
                    $this->getExtraBonus('school',$v['user_id'],1000);
                elseif($k==1)
                    $this->getExtraBonus('school',$v['user_id'],500);
                elseif($k==2)
                    $this->getExtraBonus('school',$v['user_id'],100);
                else
                    continue;
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
        // 現在的時間
        $now = new DateTime();

        // 找出這週三
        $thisWednesday = clone $now;
        $thisWednesday->modify('this week wednesday 12:00:00');

        // 若今天已經是週三中午後，則保留本週三；否則推到下週
        if ($now >= $thisWednesday) {
            $end = $thisWednesday;
        } else {
            $thisWednesday->modify('-7 days');
            $end = clone $thisWednesday;
        }

        // 上週三中午 = 這週三中午 - 7 天
        $start = clone $end;
        $start->modify('-7 days');

        $date['start'] = $start->format('Y-m-d 12:00:00');
        $date['end'] = $end->format('Y-m-d 11:59:59');

        return $date;
    }

}
