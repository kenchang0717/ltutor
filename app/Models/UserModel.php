<?php
namespace App\Models;

use CodeIgniter\Model;
use Config\Database;
use CodeIgniter\I18n\Time;

class UserModel extends Model
{
    protected $table      = 'user_users';
    protected $primaryKey = 'id';
    protected $allowedFields = ['bonus_points','updated_at']; // 可插入欄位
    protected $useTimestamps = true;       // 如果表中有 created_at / updated_at 欄位

    public function getBonusBySchoolByWeek(string $school,string $startStr,string $endStr)
    {
        $data = $this->select('user_users.school_name,SUM(user_points_transactions.points) AS BONUS')
                    ->join('user_points_transactions', 'user_users.id = user_points_transactions.user_id', 'left')
                    ->where('user_points_transactions.operation','ADD')
                    ->where('user_points_transactions.transaction_type','TASK')
                    ->where('user_users.school_name', $school)
                    ->where("CONVERT_TZ(user_points_transactions.created_at, '+00:00', '+08:00') >=", $startStr)
                    ->where("CONVERT_TZ(user_points_transactions.created_at, '+00:00', '+08:00') <=", $endStr)
                    // ->where('user_points_transactions.created_at >=', $startStr)
                    // ->where('user_points_transactions.created_at <=', $endStr)
                    ->findAll();
        return $data;
    }

    public function getBonusByUserByWeek(int $uid,string $startStr,string $endStr)
    {
        $data = $this->select('user_users.id,SUM(user_points_transactions.points) AS BONUS')
                    ->join('user_points_transactions', 'user_users.id = user_points_transactions.user_id', 'left')
                    ->where('user_points_transactions.operation','ADD')
                    ->where('user_points_transactions.transaction_type','TASK')
                    ->where('user_users.id', $uid)
                    ->where("CONVERT_TZ(user_points_transactions.created_at, '+00:00', '+08:00') >=", $startStr)
                    ->where("CONVERT_TZ(user_points_transactions.created_at, '+00:00', '+08:00') <=", $endStr)
                    // ->where('user_points_transactions.created_at >=', $startStr)
                    // ->where('user_points_transactions.created_at <=', $endStr)
                    ->find();
        return $data[0];
    }

    public function updateBonus(int $uid,int $bonus,int $before)
    {
        $db = Database::connect();

        // 啟動
        $db->transBegin();
        try {

            $utcNow = Time::now('UTC')->toDateTimeString();

            $data = [
            'bonus_points' => $bonus + $before,
            'updated_at' => $utcNow,
        ];
            $this->update($uid,$data);

            // 沒錯誤就提交
            if ($db->transStatus() === false) {
                $db->transRollback();
                return "Error";
            } else {
                $db->transCommit();
                return "success";
            }
        }catch (\Exception $e) {
            // 發生例外錯誤，Rollback
            $db->transRollback();
            return "Error：" . $e->getMessage();
        }
    }

    public function getUserInfo(int $uid)
    {
        $data = $this->select('*')
                    ->where('id', $uid)
                    ->find();
           
        return $data[0];
    }

    public function getUid(string $email)
    {
        $data = $this->select('id')
                    ->where('email', $email)
                    ->find();
           
        return $data[0]['id'];
    }

    public function getSchoolList()
    {
        $data = $this->distinct()
                    ->select('school_name')
                    ->where('school_name !=', '')
                    ->findAll();
        return $data;
    }

    public function getUidBySchool(string $school)
    {
        $res = $this->select('id')
                    ->where('school_name', $school)
                    ->findAll();

        $data = array_column($res, 'id');

        return $data;
    }
}

?>