<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Suspension extends Model
{
    use HasFactory;

    protected $primaryKey = 'SuspensionID';

    protected $fillable = [
        'SuspendedAt',
        'Reason',
        'StudentID',
    ];

    public function student()
    {
        return $this->belongsTo(User::class, 'StudentID', 'id');
    }
}