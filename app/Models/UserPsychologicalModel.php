<?php
namespace App\Models;

use CodeIgniter\Model;
use Config\Database;
use CodeIgniter\I18n\Time;

class UserPsychologicalModel extends Model
{
    protected $table      = 'user_psychological';
    protected $primaryKey = 'id';
    protected $allowedFields = ['uid','email','is_register','created_at']; // 可插入欄位
    // protected $useTimestamps = true;       // 如果表中有 created_at / updated_at 欄位

    public function add(int $uid,string $email,int $is_register)
    {
        $data = [
            'uid'  => $uid,
            'email' => $email,
            'is_register' => $is_register,
            'created_at'  => Time::now('UTC')->toDateTimeString(),
        ];

        $this->insert($data);
        return true;
    }

    public function checkEmailExist(string $email)
    {
        $today = date('Y-m-d'); // 今天日期 (不含時間)
        $data = $this->select('*')
                    ->where('email', $email)
                    ->where('DATE(created_at)', $today) // 檢查 created_at 是否為今天
                    ->find();

        if(count($data)==0)
            return $email;
    }

        public function getLog(string $date)
    {
        // $date 格式假設是 '2025-09-18'
        $data = $this->select('*')
                    ->where('DATE(created_at)', $date) // 抓指定日期的資料
                    ->where('is_register', 1)
                    ->find();

        return $data;
    }
}

?>