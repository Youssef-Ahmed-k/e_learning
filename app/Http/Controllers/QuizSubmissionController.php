<?php

namespace App\Http\Controllers;

use App\Models\Answer;
use App\Models\CheatingScore;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizResult;
use App\Models\StudentAnswer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class QuizSubmissionController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
        $this->middleware('role:user');
    }

    //** Submit a quiz
    public function submitQuiz(Request $request, $quizId)
    {
        $validated = $request->validate([
            'answers' => 'required|array', // Ensure answers are provided as an array
            'answers.*.question_id' => 'required|exists:questions,QuestionID', // Ensure each question exists
            'answers.*.answer' => 'required|exists:answers,AnswerText', // Ensure the selected answer exists
        ]);

        try {
            DB::beginTransaction();

            $studentId = auth()->user()->id;
            $quiz = Quiz::findOrFail($quizId); // Ensure the quiz exists
            $totalScore = 0; // Initialize total score

            foreach ($validated['answers'] as $answerData) {
                $question = Question::with('answers')->findOrFail($answerData['question_id']);

                // Retrieve the selected answer
                $selectedAnswer = Answer::where('AnswerText', $answerData['answer'])
                    ->where('QuestionID', $question->QuestionID)
                    ->firstOrFail();

                // Award marks if the answer is correct
                if ($selectedAnswer->IsCorrect) {
                    $totalScore += $question->Marks;
                }

                // Store the student's answer
                StudentAnswer::create([
                    'StudentId' => $studentId,
                    'QuestionId' => $question->QuestionID,
                    'SelectedAnswerId' => $selectedAnswer->AnswerID,
                ]);
            }

            // Calculate the total marks for the quiz
            $maxScore = Question::where('QuizID', $quizId)->sum('Marks');
            $percentage = ($maxScore > 0) ? ($totalScore / $maxScore) * 100 : 0;
            $passed = $percentage >= 50; // Consider 50% as the passing mark

            // Get cheating score
            $cheatingScore = CheatingScore::where('student_id', $studentId)
                ->where('quiz_id', $quizId)
                ->first();

            // Store the student's quiz result
            QuizResult::create([
                'Score' => $totalScore,
                'Percentage' => $percentage,
                'Passed' => $passed,
                'SubmittedAt' => now(),
                'StudentID' => $studentId,
                'QuizID' => $quiz->QuizID,
                'CheatingScore' => $cheatingScore ? $cheatingScore->score : 0,
            ]);

            DB::commit();
            return response()->json([
                'status' => 200,
                'message' => 'Quiz submitted successfully',
                'cheating_score' => $cheatingScore ? $cheatingScore->score : 0,
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Something went wrong', 'error' => $e->getMessage()], 500);
        }
    }
}
