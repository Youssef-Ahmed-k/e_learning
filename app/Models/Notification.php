<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;

    protected $primaryKey = 'NotificationID';

    protected $fillable = [
        'Message',
        'SendAt',
        'RecipientID',
        'ReadAt',
    ];

    public function recipient()
    {
        return $this->belongsTo(User::class, 'RecipientID', 'id');
    }
}