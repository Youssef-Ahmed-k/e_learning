<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudentAnswer extends Model
{
    use HasFactory;

    protected $primaryKey = 'StudentAnswerId';

    protected $fillable = [
        'StudentId',
        'QuestionId',
        'SelectedAnswerId',
    ];

    public function student()
    {
        return $this->belongsTo(User::class, 'StudentId', 'id');
    }

    public function question()
    {
        return $this->belongsTo(Question::class, 'QuestionId', 'QuestionID');
    }

    public function answer()
    {
        return $this->belongsTo(Answer::class, 'SelectedAnswerId', 'AnswerID');
    }
}