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
        $week = (!isset($json['week']) || $json['week']=='')?1:$json['week'];
        $time = $this->getActionDate($week);
       
        if(!isset($json['school']) || $json['school']==''){
            $userModel = new UserModel();
            $where = [
            'id' => $GLOBALS['uid'],
        ];
        $user = $userModel->where($where)->find();
        $school = empty($user[0]['school_name'])?'':$user[0]['school_name'];
        }else{
            $school = $json['school'];
        }

        $userModel = new UserModel();
        $record = $userModel->getBonusBySchoolByWeek($school,$time['start'],$time['end']);

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

        $num=0;
        $now=0;
        foreach($time as $k => $v){
            if($num == 0)
                $week=99;
            else
                $week=$num;

            $userModel = new UserModel();
            $data = $userModel->getBonusByUserByWeek($uid,$v['start'],$v['end']);
            if($v['start']<=date('Y-m-d H:i:s') && $v['end']>=date('Y-m-d H:i:s')){
                $now = $week;
            }
           
            if($data['BONUS'] == '')  
                $data['BONUS'] = 0;
            $bonus[$week] = $data['BONUS'];
            $num++;
        }

        $data = [
        'status'  => true,
        'data'  => ['data'=>$bonus,'now'=>(string)$now],
        'message' => 'success'
        ];

        return $this->response->setJSON($data);
    }

    public function getBonusInfo()
    {
        $uid = 19;    
        $userModel = new UserModel();
        $userInfo = $userModel->getUserInfo($uid);

        if (date('w') >= 4) {
            $lastWednesday = date('Y-m-d', strtotime('1 thursday ago'));
        }else{
            $lastWednesday = date('Y-m-d', strtotime('last thursday'));
        }

        $redis = new RedisLibrary();
        $time = $this->getActionDate();
        $num=0;
        foreach($time as $k => $v){
            if($num == 0)
                $week=99;
            else
                $week=$num;

            $uget[$week] = 0;
            $sget[$week] = 0;
            $slv[$week] = 0;
            if(date('Y-m-d', strtotime($v['start'])) == $lastWednesday){//找出在活動七周內的時間
                $ures = $redis->get('getExtraBonusByUser:'.$lastWednesday);
                $uinfo = json_decode($ures,true);
                if(!empty($uinfo['record'])){
                    foreach($uinfo['record'] as $k => $v){
                        if($v['user_id'] == $uid)//找出領取用戶比賽紅利用戶
                            $uget[$week] = 1;
                    }
                }
            
                $sres = $redis->get('getExtraBonusBySchool'.$userInfo['school_name'].':'.$lastWednesday);
                $sinfo = json_decode($sres,true);
                if(!empty($sinfo['record'])){
                    foreach($sinfo['record'] as $k => $v){
                        if($v['user_id'] == $uid){//找出領取學校比賽紅利用戶
                            $sget[$week] = 1;
                            $slv[$week] = $k+1;
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

    public function getActionDate(int $num = 0)
    {
        $weeks = [];

            $customStartDates = [
                1 => '2025-07-03 12:00:00',
                2 => '2025-07-10 12:00:00',
                3 => '2025-07-17 12:00:00',
                4 => '2025-07-24 12:00:00',
                5 => '2025-07-31 12:00:00',
                6 => '2025-08-07 12:00:00',
                7 => '2025-08-11 12:00:00', // ← 注意：第 7 週起始非週四
                8 => '2025-08-21 12:00:00',
            ];

            if($num==0){
                foreach ($customStartDates as $weekNo => $startStr) {
                    $start = Time::parse($startStr, $this->tz);
                    $end = Time::parse($start->toDateTimeString(), $this->tz)->addDays(6)->setTime(11, 59, 59);

                    $weeks[] = [
                        // 'week'     => $weekNo,
                        // 'start'    => $start,
                        // 'end'      => $end,
                        'start' => $start->toDateTimeString(),
                        'end'   => $end->toDateTimeString(),
                    ];
                }

                return $weeks;
            }else{
                if($num == 99)
                    $start = Time::parse($customStartDates[1], $this->tz);
                else
                    $start = Time::parse($customStartDates[$num+1], $this->tz);

                $end = Time::parse($start->toDateTimeString(), $this->tz)->addDays(6)->setTime(11, 59, 59);
                $weeks = [
                        'start' => $start->toDateTimeString(),
                        'end'   => $end->toDateTimeString(),
                    ];

                return $weeks;    
            } 
    }
}

