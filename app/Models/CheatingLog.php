<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CheatingLog extends Model
{
    use HasFactory;

    protected $primaryKey = 'Log_id';

    protected $fillable = [
        'SuspiciousBehavior',
        'IsReviewed',
        'StudentID',
        'QuizID',
        'DetectedAt',
        'image_path',
    ];

    public function student()
    {
        return $this->belongsTo(User::class, 'StudentID', 'id');
    }

    public function quiz()
    {
        return $this->belongsTo(Quiz::class, 'QuizID', 'QuizID');
    }
}