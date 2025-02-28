<?php

namespace App\Http\Controllers;

use App\Models\Answer;
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

    /**
     * Submit a quiz
     */
    public function submitQuiz(Request $request, $quizId)
    {
        $validated = $request->validate([
            'answers' => 'required|array',
            'answers.*.question_id' => 'required|exists:questions,QuestionID',
            'answers.*.answer' => 'required|exists:answers,AnswerText',
        ]);

        try {
            DB::beginTransaction();

            $studentId = auth()->user()->id;
            $quiz = Quiz::findOrFail($quizId);

            // Process student answers and calculate score
            $scoreData = $this->processStudentAnswers($studentId, $validated['answers']);

            // Store the quiz result
            $this->storeQuizResult($studentId, $quizId, $scoreData);

            DB::commit();

            return response()->json([
                'message' => 'Quiz submitted successfully',
                'score' => $scoreData['totalScore'],
                'percentage' => $scoreData['percentage'],
                'passed' => $scoreData['passed']
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to submit quiz', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Process student answers and calculate score
     */
    private function processStudentAnswers($studentId, $answers)
    {
        $totalScore = 0;

        foreach ($answers as $answerData) {
            $question = Question::with('answers')->findOrFail($answerData['question_id']);

            // Find the selected answer
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

        // Calculate percentage and determine if passed
        $maxScore = Question::where('QuizID', $question->QuizID)->sum('Marks');
        $percentage = ($maxScore > 0) ? ($totalScore / $maxScore) * 100 : 0;
        $passed = $percentage >= 50; // Consider 50% as the passing mark

        return [
            'totalScore' => $totalScore,
            'maxScore' => $maxScore,
            'percentage' => $percentage,
            'passed' => $passed
        ];
    }

    /**
     * Store the quiz result
     */
    private function storeQuizResult($studentId, $quizId, $scoreData)
    {
        return QuizResult::create([
            'Score' => $scoreData['totalScore'],
            'Percentage' => $scoreData['percentage'],
            'Passed' => $scoreData['passed'],
            'SubmittedAt' => now(),
            'StudentID' => $studentId,
            'QuizID' => $quizId,
        ]);
    }
}