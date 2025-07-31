<?php

namespace App\Controllers;

use App\Models\UserModel;
use App\Models\PointModel;
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
        $uid = $GLOBALS['uid'];    
        $time = $this->getBonusDate();
        foreach($time as $k => $v){
            $uget[$k] = 0;
            $sget[$k] = 0;
            $slv[$k] = 0;

            $pointModel = new PointModel();
            $userlExist = $pointModel->checkBonusExist($uid,'個人任務獎勵',$v['start'],$v['end']);
            $schoolExist = $pointModel->checkBonusExist($uid,'學校任務獎勵',$v['start'],$v['end']);

            if(count($userlExist)>0){
                $uget[$k] = 1;
            }

            if(count($schoolExist)>0){
                $sget[$k] = 1;
                if($schoolExist[0]['points']=='1000')
                    $slv[$k] = 1;
                elseif($schoolExist[0]['points']=='500')
                    $slv[$k] = 2;
                elseif($schoolExist[0]['points']=='100')
                    $slv[$k] = 3;
            }
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

    public function getBonusDate(int $num = 0)
    {
        $weeks = [];

            $customStartDates = [
                1 => '2025-07-16 12:00:00',
                2 => '2025-07-23 12:00:00',
                3 => '2025-07-30 12:00:00',
                4 => '2025-08-06 12:00:00',
                5 => '2025-08-13 12:00:00',
                6 => '2025-08-20 12:00:00',
                7 => '2025-08-27 12:00:00', 
            ];

            if($num==0){
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
            }else{
                $start = Time::parse($customStartDates[$num], $this->tz);
                $end = Time::parse($start->toDateTimeString(), $this->tz)->addDays(7)->setTime(11, 59, 59);
                $weeks = [
                        'start' => $start->toDateTimeString(),
                        'end'   => $end->toDateTimeString(),
                    ];

                return $weeks;    
            } 
    }
}

