<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class QuizResult extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
        $this->middleware('role:professor', ['except' => ['getStudentQuizzes', 'startQuiz', 'submitQuiz', 'getQuizResult', 'getStudentQuizzesWithResults', 'compareStudentAnswers', 'getSubmittedQuizzes']]);
        $this->middleware('role:user', ['only' => ['getStudentQuizzes', 'startQuiz', 'submitQuiz', 'getQuizResult', 'getStudentQuizzesWithResults', 'compareStudentAnswers', 'getSubmittedQuizzes']]);
    }
}