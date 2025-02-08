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

    public static function boot()
    {
        parent::boot();

        // When a question is created or updated, recalculate the quiz's total marks
        static::saved(function ($question) {
            $question->quiz->calculateTotalMarks();
        });

        // When a question is deleted, recalculate the quiz's total marks
        static::deleted(function ($question) {
            $question->quiz->calculateTotalMarks();
        });
    }

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
