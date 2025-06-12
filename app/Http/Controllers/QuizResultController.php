<?php

namespace App\Http\Controllers;

use App\Models\Quiz;
use App\Models\QuizResult;
use App\Models\StudentQuiz;
use App\Models\User;
use App\Models\CheatingScore;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class QuizResultController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
        $this->middleware('role:professor', ['except' => ['getQuizResult', 'getStudentQuizzesWithResults']]);
        $this->middleware('role:user', ['only' => ['getQuizResult', 'getStudentQuizzesWithResults']]);
    }

    public function getQuizScores($quizId)
    //for professor
    {
        try {
            // Retrieve the quiz results for the specified quiz
            $quizResults = QuizResult::where('QuizID', $quizId)->get();

            if ($quizResults->isEmpty()) {
                return response()->json(['message' => 'No results found for this quiz'], 404);
            }

            // Create an array containing the student name and score
            $scores = $quizResults->map(function ($result) {
                $student = User::find($result->StudentID); // Retrieve the student data
                return [
                    'student_name' => $student ? $student->name : 'Unknown', // Get the student name or show "Unknown"
                    'score' => $result->Score,
                    'percentage' => $result->Percentage,
                    'passed' => $result->Passed,
                ];
            });

            // Return the result
            return response()->json([
                'quiz_id' => $quizId,
                'students_scores' => $scores,
            ]);
        } catch (\Exception $e) {
            // Catch any exceptions and return an error message
            return response()->json([
                'message' => 'An error occurred while retrieving the quiz scores.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getQuizResult($quizId)
    //for student
    {
        try {
            $user = auth()->user()->id; // Get the authenticated user

            // Find the quiz result for the student
            $quizResult = QuizResult::where('QuizID', $quizId)->where('StudentID', $user)->first();

            if (!$quizResult) {
                return response()->json(['message' => 'No quiz result found'], 404);
            }

            // Check the cheating score
            $cheatingScore = CheatingScore::where('student_id', $user)
                ->where('quiz_id', $quizId)
                ->first();

            if ($cheatingScore && $cheatingScore->score == 100) {
                // Get the quiz
                $quiz = Quiz::with('course')->find($quizId);
                $professorEmail = null;
                if ($quiz && $quiz->course) {
                    $professor = User::find($quiz->course->ProfessorID);
                    $professorEmail = $professor ? $professor->email : null;
                }

                return response()->json([
                    'message' => 'You have been flagged for cheating in this quiz. Please contact your professor.',
                    'score' => 0,
                    'percentage' => 0,
                    'passed' => false,
                    'professor_email' => $professorEmail,
                    'cheating_score' => $cheatingScore->score,
                ], 200);
            }

            // Return the result with pass/fail status
            return response()->json([
                'score' => $quizResult->Score,
                'percentage' => $quizResult->Percentage,
                'passed' => $quizResult->Passed,
            ]);
        } catch (\Exception $e) {
            // Catch any exceptions and return an error message
            return response()->json([
                'message' => 'An error occurred while retrieving the quiz result.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getStudentQuizzesWithResults()
    {
        try {
            $studentId = auth()->user()->id;

            // Retrieve quizzes taken by the student with related quiz details and results
            $quizzes = StudentQuiz::where('student_id', $studentId)
                ->with([
                    'quiz' => function ($query) {
                        $query->select('Title', 'Description', 'TotalMarks', 'CourseID');
                    },
                    'quiz.course' => function ($query) {
                        $query->select('CourseID', 'CourseName', 'CourseCode');
                    },
                    'quiz.quizResults' => function ($query) use ($studentId) {
                        $query->where('StudentID', $studentId)
                            ->select('Score', 'Percentage', 'Passed', 'SubmittedAt');
                    }
                ])
                ->get()
                ->map(function ($studentQuiz) use ($studentId) {
                    // Check cheating score for each quiz
                    $cheatingScore = CheatingScore::where('student_id', $studentId)
                        ->where('quiz_id', $studentQuiz->quiz_id)
                        ->first();

                    if ($cheatingScore && $cheatingScore->score == 100) {
                        $studentQuiz->quizResults->each(function ($result) {
                            $result->Score = 0;
                            $result->Percentage = 0;
                            $result->Passed = false;
                        });
                    }

                    return $studentQuiz;
                });

            return response()->json([
                'success' => true,
                'message' => 'Student quizzes retrieved successfully',
                'data' => $quizzes
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving student quizzes',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getEndedQuizzesWithResults(Request $request)
    {
        try {
            $professorId = Auth::id();
            $search = $request->query('search', '');
            $sortBy = $request->query('sortBy', 'QuizDate');
            $sortOrder = $request->query('sortOrder', 'desc');
            $courseId = $request->query('courseId');

            $query = Quiz::with(['quizResults', 'course'])
                ->whereHas('course', function ($query) use ($professorId) {
                    $query->where('ProfessorID', $professorId);
                })
                ->whereNotNull('EndTime')
                ->where('EndTime', '<', Carbon::now('Africa/Cairo'))
                ->has('quizResults');

            // Apply search filter
            if ($search) {
                $query->where('Title', 'LIKE', "%{$search}%");
            }

            // Apply course filter
            if ($courseId) {
                $query->where('CourseID', $courseId);
            }

            // Apply sorting
            $allowedSortFields = ['QuizDate', 'Title', 'TotalMarks'];
            if (in_array($sortBy, $allowedSortFields)) {
                $query->orderBy($sortBy, $sortOrder);
            }

            $endedQuizzes = $query->paginate(10);

            $mappedData = $endedQuizzes->through(function ($quiz) {
                return [
                    'quiz_details' => [
                        'id' => $quiz->QuizID,
                        'title' => $quiz->Title,
                        'description' => $quiz->Description,
                        'duration' => $quiz->Duration,
                        'start_time' => $quiz->StartTime,
                        'end_time' => $quiz->EndTime,
                        'quiz_date' => $quiz->QuizDate,
                        'total_marks' => $quiz->TotalMarks,
                    ],
                    'course_details' => [
                        'id' => $quiz->course->CourseID,
                        'name' => $quiz->course->CourseName,
                        'code' => $quiz->course->CourseCode,
                    ],
                    'students_count' => $quiz->quizResults->count(),
                ];
            });

            return response()->json([
                'data' => $mappedData->items(),
                'pagination' => [
                    'current_page' => $endedQuizzes->currentPage(),
                    'total_pages' => $endedQuizzes->lastPage(),
                    'total_items' => $endedQuizzes->total(),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 500,
                'message' => 'Something went wrong',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}