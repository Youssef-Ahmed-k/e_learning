<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CheatingScore extends Model
{
    use HasFactory;

    protected $fillable = ['student_id', 'quiz_id', 'score'];

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id', 'id');
    }

    public function quiz()
    {
        return $this->belongsTo(Quiz::class, 'quiz_id', 'QuizID');
    }
}
