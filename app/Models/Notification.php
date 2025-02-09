<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;
    public $timestamps = false;
    protected $primaryKey = 'NotificationID';
    protected $fillable = [
        'Message',
        'SendAt',
        'RecipientID',
        'is_read',
    ];

    public function recipient()
    {
        return $this->belongsTo(User::class, 'RecipientID', 'id');
    }
}