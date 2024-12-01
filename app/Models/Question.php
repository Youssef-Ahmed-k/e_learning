<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Question extends Model
{
    use HasFactory;

    protected $primaryKey = 'QuestionID';

    protected $fillable = [
        'Content',
        'Type',
        'Marks',
        'image',
        'QuizID',
    ];

    public function quiz()
    {
        return $this->belongsTo(Quiz::class, 'QuizID', 'QuizID');
    }

    public function answers()
    {
        return $this->hasMany(Answer::class, 'QuestionID', 'QuestionID');
    }

    public function studentAnswers()
    {
        return $this->hasMany(StudentAnswer::class, 'QuestionId', 'QuestionID');
    }
}