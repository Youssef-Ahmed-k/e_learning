<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuizResult extends Model
{
    use HasFactory;

    protected $primaryKey = 'QuizResultID';

    protected $fillable = [
        'Score',
        'Percentage',
        'Passed',
        'SubmittedAt',
        'StudentID',
        'QuizID',
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