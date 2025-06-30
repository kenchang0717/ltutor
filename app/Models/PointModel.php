<?php
namespace App\Models;

use CodeIgniter\Model;
use Config\Database;

class PointModel extends Model
{
    protected $table      = 'user_points_transactions';
    protected $primaryKey = 'id';
    protected $allowedFields = [
        'user_id', 
        'points', 
        'before_points', 
        'after_points', 
        'point_balance', 
        'type', 
        'transaction_type', 
        'operation',
        'title',
        'description',
        'success',
        'error_message',
    ];  // 可插入欄位
    protected $useTimestamps = true;       // 如果表中有 created_at / updated_at 欄位

    public function addBonusLog(int $uid,int $bonus,string $type,int $before)
    {
        $db = Database::connect();

        // 啟動
        $db->transBegin();
        try {
            if($type == 'user')
                $description = '個人';
            else
                $description = '學校';

            $data = [
            'user_id' => $uid, 
            'points'  => $bonus, 
            'before_points'  => $before, 
            'after_points'  => $before + $bonus, 
            'point_balance'  => $bonus, 
            'type'  => 'BONUS', 
            'transaction_type'  => 'SYSTEM', 
            'operation'  => 'ADD',
            'title'  => '領取網頁活動任務獎勵',
            'description'  => $description.'任務獎勵',
            'success'  => 1,
            ];

            $this->insert($data);
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

}

?>