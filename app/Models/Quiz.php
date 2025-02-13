<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Quiz extends Model
{
    use HasFactory;

    protected $primaryKey = 'QuizID';

    protected $fillable = [
        'Title',
        'Description',
        'Duration',
        'StartTime',
        'EndTime',
        'QuizDate',
        'LockdownEnabled',
        'CourseID',
        'TotalMarks',
    ];

    public function course()
    {
        return $this->belongsTo(Course::class, 'CourseID', 'CourseID');
    }

    public function questions()
    {
        return $this->hasMany(Question::class, 'QuizID', 'QuizID');
    }

    public function quizResults()
    {
        return $this->hasMany(QuizResult::class, 'QuizID', 'QuizID');
    }

    public function studentQuizzes()
    {
        return $this->hasMany(StudentQuiz::class, 'quiz_id', 'QuizID');
    }

    public function cheatingLogs()
    {
        return $this->hasMany(CheatingLog::class, 'QuizID', 'QuizID');
    }

    public function calculateTotalMarks()
    {
        $this->TotalMarks = $this->questions()->sum('Marks');
        $this->save();
    }
}
