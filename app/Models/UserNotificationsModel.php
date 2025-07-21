<?php
namespace App\Models;

use CodeIgniter\Model;
use CodeIgniter\I18n\Time;

class UserNotificationsModel extends Model
{
    protected $table      = 'user_notifications';
    protected $primaryKey = 'id';
    protected $allowedFields = ['user_id','title','content','is_read','is_delete','notification_type','is_show','created_at','updated_at']; // 可插入欄位
    protected $useTimestamps = true;       // 如果表中有 created_at / updated_at 欄位

    public function add(array $data)
    {
        $data = [
            'user_id'  => $data['user_id'],
            'title' => $data['title'],
            'content'  => $data['content'],
            'is_read' => 0,
            'is_delete'  => 0,
            'notification_type' => 'General',
            'is_show' => 1,
            'created_at'  => Time::now('UTC')->toDateTimeString(),
            'updated_at'  => Time::now('UTC')->toDateTimeString(),
        ];

        $this->insert($data);

        return true;
    }
}

?>