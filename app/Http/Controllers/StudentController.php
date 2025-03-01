<?php

namespace App\Http\Controllers;

use App\Models\Material;
use App\Models\Course;
use App\Models\CourseRegistration;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizResult;
use App\Models\StudentAnswer;
use App\Models\StudentQuiz;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StudentController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
        $this->middleware('role:user');
    }

    // Allows a student to view the courses they are registered for
    public function viewRegisteredCourses()
    {
        $studentID = auth()->id();
        $registeredCourses = CourseRegistration::with('course')
            ->where('StudentID', $studentID)
            ->get();

        return response()->json([
            'data' => [
                'registeredCourses' => $registeredCourses,
            ]
        ]);
    }

    // View course materials
    public function viewCourseMaterials($courseID)
    {
        try {
            // Validate the course ID directly from the URL parameter
            $course = Course::where('CourseID', $courseID)->first();

            if (!$course) {
                return response()->json([
                    'message' => 'Invalid course ID',
                ], 404);
            }

            // Check if the authenticated student is enrolled in the course
            $studentID = auth()->id();
            $isEnrolled = CourseRegistration::where('CourseID', $courseID)
                ->where('StudentID', $studentID)
                ->exists();

            if (!$isEnrolled) {
                return response()->json([
                    'message' => 'You are not enrolled in this course',
                ], 403);
            }

            // Fetch course materials
            $courseMaterials = Material::where('CourseID', $courseID)->get();

            return response()->json([
                'data' => [
                    'courseMaterials' => $courseMaterials,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Something went wrong',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function hasStudentStartedQuiz($studentId, $quizId)
    {
        return StudentQuiz::where('student_id', $studentId)->where('quiz_id', $quizId)->exists();
    }

    public function getAvailableQuizzes()
    //for student
    {
        try {
            $studentId = auth()->user()->id;

            // Get courses the student is enrolled in
            $courses = CourseRegistration::where('StudentID', $studentId)->pluck('CourseID');

            // Get quizzes for the enrolled courses along with course name and code
            $quizzes = Quiz::join('courses', 'quizzes.CourseID', '=', 'courses.CourseID') // Join with courses table
                ->whereIn('quizzes.CourseID', $courses) // Specify table for CourseID
                ->select(
                    'quizzes.QuizID',
                    'quizzes.Title',
                    'quizzes.Description',
                    'quizzes.StartTime',
                    'quizzes.EndTime',
                    'quizzes.CourseID',
                    'quizzes.Duration',
                    'quizzes.QuizDate',
                    'quizzes.LockdownEnabled',
                    'quizzes.TotalMarks',
                    'courses.CourseName',
                    'courses.CourseCode'
                )
                ->get();

            // Filter out quizzes the student has already started or quizzes whose end time has passed
            $filteredQuizzes = $quizzes->reject(function ($quiz) use ($studentId) {
                // Check if the student has started the quiz or if the quiz end time has passed
                return $this->hasStudentStartedQuiz($studentId, $quiz->QuizID)
                    || $quiz->EndTime <= now();
            });

            return response()->json(['quizzes' => $filteredQuizzes->values()], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Something went wrong', 'error' => $e->getMessage()], 500);
        }
    }

    public function getSubmittedQuizzes()
    {
        try {
            // Get the authenticated student ID
            $studentId = Auth::id();

            // Fetch quizzes where the student has taken (student_quizzes) or submitted (quiz_results)
            $quizzes = Quiz::with('course')
                ->WhereHas('quizResults', function ($query) use ($studentId) {
                    $query->where('StudentID', $studentId);
                })
                ->paginate(9);

            // Format response
            $mappedData = $quizzes->through(function ($quiz) {
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
                ];
            });

            return response()->json([
                'data' => $mappedData->items(),
                'pagination' => [
                    'current_page' => $quizzes->currentPage(),
                    'total_pages' => $quizzes->lastPage(),
                    'total_items' => $quizzes->total(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 500,
                'message' => 'Something went wrong',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function startQuiz($id)
    {
        try {
            $quiz = Quiz::with('questions.answers')->findOrFail($id);

            // Get current time in Egypt timezone
            $currentDateTime = Carbon::now('Africa/Cairo');
            $startTime = Carbon::parse($quiz->StartTime)->setTimezone('Africa/Cairo');
            $endTime = Carbon::parse($quiz->EndTime)->setTimezone('Africa/Cairo');

            // Check if the quiz is not yet active
            if ($currentDateTime->lt($startTime)) {
                $remainingTime = $currentDateTime->diffForHumans($startTime);
                return response()->json([
                    'message' => "Quiz will start in $remainingTime"
                ], 403);
            }

            // Check if the quiz has already ended
            if ($currentDateTime->gt($endTime)) {
                return response()->json(['message' => 'Quiz has already ended'], 403);
            }

            // Get student ID
            $studentId = auth()->user()->id;

            // Check if the student has already submitted this quiz
            $existingResult = QuizResult::where('StudentID', $studentId)
                ->where('QuizID', $id)
                ->whereNotNull('SubmittedAt') // Ensure the quiz was submitted
                ->first();

            if ($existingResult) {
                return response()->json(['message' => 'You have already completed this quiz and cannot start again'], 403);
            }

            // Record that the student has started the quiz (only if it's the first time)
            StudentQuiz::firstOrCreate([
                'student_id' => $studentId,
                'quiz_id' => $id,
            ]);
            $quizData = [
                'Title' => $quiz->Title,
                'Description' => $quiz->Description,
                'Duration' => $quiz->Duration,
                'StartTime' => $quiz->StartTime,
                'EndTime' => $quiz->EndTime,
                'QuizDate' => $quiz->QuizDate,
                'TotalMarks' => $quiz->TotalMarks,
                'CourseName' => $quiz->course->CourseName,
                'CourseCode' => $quiz->course->CourseCode,
                'questions' => $quiz->questions->map(function ($question) {
                    return [
                        'Content' => $question->Content,
                        'Type' => $question->Type,
                        'Marks' => $question->Marks,
                        'answers' => $question->answers->map(function ($answer) {
                            return [
                                'AnswerText' => $answer->AnswerText
                            ];
                        }),
                    ];
                }),
            ];
            return response()->json([
                'status' => 200,
                'message' => 'Quiz started successfully',
                'quiz' => $quizData
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Something went wrong',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function compareStudentAnswers(Request $request, $quizId)
    {
        try {
            $perPage = $request->input('per_page', 5); // Default to 5 questions per page
            $page = $request->input('page', 1);

            // Fetch the quiz
            $quiz = Quiz::findOrFail($quizId);

            // Get the authenticated student ID
            $studentId = auth()->id();
            if (!$studentId) {
                return response()->json(['status' => 401, 'message' => 'Unauthorized'], 401);
            }

            // Paginate quiz questions with answers
            $questions = Question::where('QuizID', $quizId)
                ->with('answers')
                ->paginate($perPage, ['*'], 'page', $page);

            // Fetch student answers for paginated questions
            $studentAnswers = StudentAnswer::whereIn('QuestionID', $questions->pluck('QuestionID')->toArray())
                ->where('StudentID', $studentId)
                ->get()
                ->keyBy('QuestionID');

            // Prepare response data
            $questionsComparison = $questions->map(function ($question) use ($studentAnswers) {
                $correctAnswerId = $question->answers->firstWhere('IsCorrect', true)?->AnswerID;
                $studentAnswer = $studentAnswers->get($question->QuestionID);

                return [
                    'question_id' => $question->QuestionID,
                    'question_text' => $question->Content,
                    'image' => $question->image,
                    'marks' => $question->Marks,
                    'answers' => $question->answers->map(function ($answer) use ($correctAnswerId, $studentAnswer) {
                        return [
                            'answer_id' => $answer->AnswerID,
                            'answer_text' => $answer->AnswerText,
                            'is_correct' => $answer->AnswerID == $correctAnswerId,
                            'is_student_choice' => optional($studentAnswer)->SelectedAnswerID == $answer->AnswerID
                        ];
                    }),
                    'student_selected_correct' => optional($studentAnswer)->SelectedAnswerID == $correctAnswerId
                ];
            });

            return response()->json([
                'status' => 200,
                'message' => 'Quiz answers compared successfully',
                'quiz' => $quiz,
                'questions' => $questionsComparison,
                'pagination' => [
                    'current_page' => $questions->currentPage(),
                    'total_pages' => $questions->lastPage(),
                    'total_items' => $questions->total(),
                    'per_page' => $questions->perPage(),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 500,
                'message' => 'Something went wrong',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}