<?php
namespace App\Models;

use CodeIgniter\Model;
use Config\Database;

class UserChangeSchoolModel extends Model
{
    protected $table      = 'logs_user_school_change_histories';
    protected $primaryKey = 'id';

    public function getUserChangeSchoolList(string $start,string $end)
    {
        $res = $this->select('user_id')
                    ->where("CONVERT_TZ(created_at, '+00:00', '+08:00') >=", $start)
                    ->where("CONVERT_TZ(created_at, '+00:00', '+08:00') <=", $end)
                    // ->where('created_at >=', $start)
                    // ->where('created_at <=', $end)
                    ->findAll();
        $data = array_column($res, 'user_id');
        $list = json_encode($data);

        return $list;
    }
}

?>