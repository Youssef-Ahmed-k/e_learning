<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;

    protected $table = 'notifications'; 
    protected $primaryKey = 'NotificationID'; 

    public $timestamps = false; 
    protected $fillable = [
        'Message',
        'SendAt',
        'RecipientID',
        'is_read',
        'type', 
        'CourseID',
    ];
    public function setSendAtAttribute($value)
    {
        if (!$this->exists) {
            $this->attributes['SendAt'] = $value;
        }
    }

    public function recipient()
    {
        return $this->belongsTo(User::class, 'RecipientID', 'id');
    }


    public function course()
    {
        return $this->belongsTo(Course::class, 'CourseID', 'CourseID');
    }
}
