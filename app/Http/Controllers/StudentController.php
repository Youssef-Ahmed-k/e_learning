<?php

namespace App\Http\Controllers;

use App\Models\CheatingLog;
use App\Models\CheatingScore;
use App\Models\Material;
use App\Models\Course;
use App\Models\CourseRegistration;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizResult;
use App\Models\StudentAnswer;
use App\Models\StudentQuiz;
use App\Models\User;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class StudentController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
        $this->middleware('role:user')->except([
            'compareStudentAnswers'
        ]);
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

    public function startQuiz($id, Request $request)
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

            // Face verification logic
            $capturedFrame = $request->input('captured_frame');
            if (!$capturedFrame) {
                return response()->json(['message' => 'A captured image is required for face verification'], 422);
            }

            // Send base64 string to FastAPI - use JSON format
            $response = Http::asJson()->post('http://localhost:8001/recognize', [
                'captured_image' => $capturedFrame,
                'user_id' => $studentId,
            ]);

            if ($response->failed()) {
                return response()->json([
                    'message' => 'Face verification failed',
                    'error' => $response->json()['detail'] ?? $response->body()
                ], 403);
            }

            $verificationResult = $response->json();
            $matches = $verificationResult['matches'] ?? [];

            if (empty($matches)) {
                return response()->json(['message' => 'No face detected in the image', 'debug' => $verificationResult], 403);
            }

            // Verify the student's identity (must match studentId and have high confidence)
            $isVerified = collect($matches)->contains(function ($match) use ($studentId) {
                return isset($match['user_id']) &&
                    $match['user_id'] == $studentId &&
                    $match['confidence'] >= 0.7;
            });

            if (!$isVerified) {
                return response()->json([
                    'message' => 'Face verification failed. You are not authorized to start this quiz.',
                    'debug' => $verificationResult,
                    'matches' => $matches
                ], 403);
            }

            // Record that the student has started the quiz (only if it's the first time)
            StudentQuiz::firstOrCreate([
                'student_id' => $studentId,
                'quiz_id' => $id,
            ]);

            // Initialize cheating score
            CheatingScore::firstOrCreate(
                ['student_id' => $studentId, 'quiz_id' => $id],
                ['score' => 0]
            );

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
            ];
            return response()->json([
                'status' => 200,
                'message' => 'Quiz started successfully',
                'quiz' => $quizData,
                'student_id' => (string) $studentId,
                'quiz_id' => $id,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Something went wrong',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function updateCheatingScore(Request $request)
    {

        try {
            $studentId = $request->input('student_id');
            $quizId = $request->input('quiz_id');
            $scoreIncrement = $request->input('score_increment');
            $suspiciousBehaviors = $request->input('alerts', []);
            $imageB64 = $request->input('image_b64');
            $answers = $request->input('answers', []);

            // Validate inputs
            if (!is_numeric($scoreIncrement) || $scoreIncrement < 0 || $scoreIncrement > 100) {
                return response()->json(['message' => 'Invalid score increment'], 400);
            }

            if ($imageB64 && !preg_match('/^data:image\/[a-z]+;base64,/', $imageB64)) {
                return response()->json(['message' => 'Invalid image format'], 400);
            }

            // Update cheating score
            $cheatingScore = CheatingScore::where('student_id', $studentId)
                ->where('quiz_id', $quizId)
                ->first();

            if (!$cheatingScore) {
                return response()->json(['message' => 'Cheating score record not found'], 404);
            }

            $newScore = min($cheatingScore->score + $scoreIncrement, 100);
            $cheatingScore->update(['score' => $newScore]);

            // Handle image if provided
            $imagePath = null;
            if ($imageB64) {
                $imageData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $imageB64));
                if ($imageData === false || empty($imageData)) {
                    Log::warning('Failed to decode image data for student_id: ' . $studentId . ', quiz_id: ' . $quizId);
                } else {
                    $filename = 'cheating_' . time() . '_' . uniqid() . '.jpg';
                    $directory = 'cheating_images';
                    if (!Storage::disk('public')->exists($directory)) {
                        Storage::disk('public')->makeDirectory($directory);
                    }
                    Storage::disk('public')->put($directory . '/' . $filename, $imageData);
                    $imagePath = Storage::disk('public')->url($directory . '/' . $filename);
                    Log::info('Image saved at: ' . $imagePath);
                }
            }

            // Log suspicious behaviors to CheatingLog
            foreach ($suspiciousBehaviors as $behavior) {
                CheatingLog::create([
                    'SuspiciousBehavior' => $behavior,
                    'IsReviewed' => false,
                    'StudentID' => $studentId,
                    'QuizID' => $quizId,
                    'DetectedAt' => now(),
                    'image_path' => $imagePath
                ]);
            }

            // Notify professor if score reaches 100 and submit the quiz automatically
            if ($newScore >= 100) {
                $quiz = Quiz::findOrFail($quizId);
                $student = User::findOrFail($studentId);
                $professor = $quiz->course->professor; // Assuming professor relation exists

                // Send notification to the professor who created the quiz
                $message = "Cheating detected for student {$student->name} in quiz {$quiz->Title}.";
                NotificationService::sendNotification($professor->id, $message);

                // Trigger quiz submission with available answers
                $request->merge(['answers' => $answers]);
                $quizSubmissionController = new QuizSubmissionController();
                $submissionResponse = $quizSubmissionController->submitQuiz($request, $quizId);

                return response()->json([
                    'message' => 'Cheating score reached 100. Quiz submission triggered.',
                    'new_score' => $newScore,
                    'auto_submitted' => true,
                    'image_path' => $imagePath,
                    'submission_response' => $submissionResponse->getData(true),
                ]);
            }

            return response()->json(['message' => 'Score updated', 'new_score' => $newScore, 'image_path' => $imagePath]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error updating score', 'error' => $e->getMessage()], 500);
        }
    }

    public function endQuiz($id, Request $request)
    {
        try {
            $studentId = auth()->user()->id;
            $cheatingScore = CheatingScore::where('student_id', $studentId)
                ->where('quiz_id', $id)
                ->first();

            return response()->json([
                'message' => 'Quiz ended',
                'cheating_score' => $cheatingScore ? $cheatingScore->score : 0
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error ending quiz', 'error' => $e->getMessage()], 500);
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
