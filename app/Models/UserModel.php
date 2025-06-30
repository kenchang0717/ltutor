<?php
namespace App\Models;

use CodeIgniter\Model;
use Config\Database;

class UserModel extends Model
{
    protected $table      = 'user_users';
    protected $primaryKey = 'id';
    protected $allowedFields = ['bonus_points']; // 可插入欄位
    protected $useTimestamps = true;       // 如果表中有 created_at / updated_at 欄位

    public function getBonusBySchoolByWeek(string $school,string $startStr,string $endStr)
    {
        $data = $this->select('user_users.school_name,SUM(user_points_transactions.point_balance) AS BONUS')
                    ->join('user_points_transactions', 'user_users.id = user_points_transactions.user_id', 'left')
                    ->where('user_points_transactions.operation','ADD')
                    ->where('user_points_transactions.transaction_type','TASK')
                    ->where('user_users.school_name', $school)
                    ->where('user_points_transactions.created_at >=', $startStr)
                    ->where('user_points_transactions.created_at <=', $endStr)
                    ->findAll();
        return $data;
    }

    public function getBonusByUserByWeek(int $uid,string $startStr,string $endStr)
    {
        $data = $this->select('SUM(user_points_transactions.point_balance) AS BONUS')
                    ->join('user_points_transactions', 'user_users.id = user_points_transactions.user_id', 'left')
                    ->where('user_points_transactions.operation','ADD')
                    ->where('user_points_transactions.transaction_type','TASK')
                    ->where('user_users.id', $uid)
                    ->where('user_points_transactions.created_at >=', $startStr)
                    ->where('user_points_transactions.created_at <=', $endStr)
                    ->find();
        return $data[0];
    }

    public function updateBonus(int $uid,int $bonus,int $before)
    {
        $db = Database::connect();

        // 啟動
        $db->transBegin();
        try {

            $data = [
            'bonus_points' => $bonus + $before,
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

    public function getSchoolList()
    {
        $data = $this->distinct()
                    ->select('school_name')
                    ->where('school_name !=', '')
                    ->findAll();
        return $data;
    }
}

?>