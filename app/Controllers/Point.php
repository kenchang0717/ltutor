<?php

namespace App\Controllers;

use App\Models\UserModel;
use DateTime;
use DateTimeZone;
use App\Libraries\RedisLibrary;
use CodeIgniter\I18n\Time;
use DateInterval;
use DatePeriod;

class Point extends BaseController {
    private $tz;
    public function __construct() {
        $this->tz = new DateTimeZone('Asia/Taipei');
    }

    public function getNewRecord()
    {
        $redis = new RedisLibrary();
        $record = $redis->get('getBonusBySchoolNow');
        $data = [
        'status'  => true,
        'data'  => json_decode($record),
        'message' => 'success'
        ];
        return $this->response->setJSON($data);
    }

    public function getLastWeekRecord()
    {
        $redis = new RedisLibrary();
        $record = $redis->get('getBonusBySchoolLastWeek');
        $data = [
        'status'  => true,
        'data'  => json_decode($record),
        'message' => 'success'
        ];
        return $this->response->setJSON($data);
    }

	public function getBonusBySchoolByWeek()
    {
        $json = $this->request->getJSON(true); 
        $week = (!isset($json['week']) || $json['week']=='')?$this->getWeekNumberOfLastWednesday():$json['week'];
        //配合此活動
        $now = Time::now(); // 使用當前時區
        $month = $now->getMonth(); // 取得數字月份（1 到 12）

        if($week>4  && $month != 8){
            return 'week is fail';
        }
       
        if(!isset($json['school']) || $json['school']==''){
            $userModel = new UserModel();
            $where = [
            'id' => $GLOBALS['uid'],
        ];
        $user = $userModel->where($where)->find();
        $school = empty($user[0]['school_name'])?'':$user[0]['school_name'];
        }

        // 找出本月第一天
        $firstDay = new DateTime('first day of this month', $this->tz);
    
        if($week!=1){
            $wednesdayCount = 0;
            // 從第一天開始找，直到找到第 X 個週三
            while ($wednesdayCount < $week) {
                if ($firstDay->format('w') == 3) { // 3 = Wednesday
                    $wednesdayCount++;
                }
                if ($wednesdayCount < $week) {
                    $firstDay->modify('+1 day');
                }
            }
        }else{
            // 如果第一天不是週三，往後加一天直到是週三（數字 3）
            while ($firstDay->format('w') != 3) {
                $firstDay->modify('+1 day');
            }
        }

        // 起始時間：該天的 00:00:00
        $start = clone $firstDay;
        $start->setTime(12, 0, 0);

        // 結束時間：該天的 23:59:59
        $end = $firstDay->modify('+7 day');
        $end->setTime(11, 59, 59);
       
        $startStr = $start->format('Y-m-d H:i:s');
        $endStr = $end->format('Y-m-d H:i:s');

        $userModel = new UserModel();
        $record = $userModel->getBonusBySchoolByWeek($school,$startStr,$endStr);

        if(!isset($record[0]['BONUS']) || $record[0]['BONUS']==''){
            $record[0]['BONUS']=0;
            $record[0]['school_name']=$school;
        }            

        $data = [
        'status'  => true,
        'data'  => $record[0],
        'message' => 'success'
        ];

        return $this->response->setJSON($data);
    }

    public function getBonusByUserByWeek()
    {
        $uid = $GLOBALS['uid'];
        $time = $this->getActionDate();

        $num=1;
        foreach($time as $k => $v){
            $userModel = new UserModel();
            $data = $userModel->getBonusByUserByWeek($uid,$v['start'],$v['end']);
            if($data['BONUS'] == '')  
                $data['BONUS'] = 0;      
            $bonus[$num] = $data['BONUS'];
            $num++;
        }

        $data = [
        'status'  => true,
        'data'  => $bonus,
        'message' => 'success'
        ];

        return $this->response->setJSON($data);
    }

    public function getBonusInfo()
    {
        $uid = $GLOBALS['uid'];    
        $userModel = new UserModel();
        $userInfo = $userModel->getUserInfo($uid);

        if (date('w') >= 4) {
            $lastWednesday = date('Y-m-d', strtotime('1 Wednesdays ago'));
        }else{
            $lastWednesday = date('Y-m-d', strtotime('last Wednesday'));
        }

        $redis = new RedisLibrary();
        $time = $this->getActionDate();
        $num=1;
        foreach($time as $k => $v){
            $uget[$num] = 0;
            $sget[$num] = 0;
            $slv[$num] = 0;
            if(date('Y-m-d', strtotime($v['start'])) == $lastWednesday){//找出在活動七周內的時間
                $ures = $redis->get('getExtraBonusByUser:'.$lastWednesday);
                $uinfo = json_decode($ures,true);
                if(!empty($uinfo['record'])){
                    foreach($uinfo['record'] as $k => $v){
                        if($v['user_id'] == $uid)//找出領取用戶比賽紅利用戶
                            $uget[$num] = 1;
                    }
                }
            
                $sres = $redis->get('getExtraBonusBySchool'.$userInfo['school_name'].':'.$lastWednesday);
                $sinfo = json_decode($sres,true);
                if(!empty($sinfo['record'])){
                    foreach($sinfo['record'] as $k => $v){
                        if($v['user_id'] == $uid){//找出領取學校比賽紅利用戶
                            $sget[$num] = 1;
                            $slv[$num] = $k+1;
                        }
                            
                    }
                }
            }
        $num++;    
        }
      
        $data = [
        'status'  => true,
        'data'  => ['user'=>$uget,'school'=>$sget,'schoolLV'=>$slv],
        'message' => 'success'
        ];

        return $this->response->setJSON($data);
    }

    public function getActionDate()
    {
         // 設定起始與結束日期
        $startDate = new DateTime(date('Y') . '-07-09'); // 2025-07-09
        $endDate   = new DateTime(date('Y') . '-08-27'); // 2025-08-27

        // 加一天讓 DatePeriod 包含最後一天
        $endDate->modify('+1 day');

        // 每週三
        $interval = new DateInterval('P1W');
        $period = new DatePeriod($startDate, $interval, $endDate);

        // 開始列出每週三與週次
        $week = 1;
        $time[$week]['start'] = '2025-07-09 12:00:00';
        foreach ($period as $date) {
            $time[$week]['end'] = $date->format('Y-m-d 11:59:59');
            $week++;
            $time[$week]['start'] = $date->format('Y-m-d 12:00:00');
        }
        unset($time[1]);
        unset($time[9]);

        return $time;
    }

    public function getWeekNumberOfLastWednesday()
    {
        // 取得上週三日期
        $lastWednesday = new DateTime('last wednesday 12:00', $this->tz);
        // 當月第一天
        $firstOfMonth = new DateTime($lastWednesday->format('Y-m-01'), $this->tz);
        // 本月第一天是星期幾 (0 = Sunday, ..., 6 = Saturday)
        $firstDayWeekday = (int) $firstOfMonth->format('w');
        // 計算當日是這個月的第幾天（1-based）
        $dayOfMonth = (int) $lastWednesday->format('j');
        // 計算週數（以週日為週的第一天）
        $adjustedDay = $dayOfMonth + $firstDayWeekday;
        $weekNumber = (int) ceil($adjustedDay / 7);

        return $weekNumber;
    }
}

