<?php

namespace App\Http\Controllers;


use App\Http\Requests\CreateQuizRequest;
use App\Http\Requests\UpdateQuizRequest;
use App\Models\Quiz;
use App\Models\Question;
use App\Models\Course;
use App\Models\CourseRegistration;
use App\Models\StudentAnswer;
use App\Models\StudentQuiz;
use App\Models\QuizResult;
use App\Models\Answer;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class QuizController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
        $this->middleware('role:professor', ['except' => ['getStudentQuizzes', 'startQuiz', 'submitQuiz', 'compareStudentAnswers', 'getSubmittedQuizzes']]);
        $this->middleware('role:user', ['only' => ['getStudentQuizzes', 'startQuiz', 'submitQuiz', 'compareStudentAnswers', 'getSubmittedQuizzes']]);
    }

    // Helper method to handle date and time logic
    protected function formatQuizDateTime($quizDate, $startTime, $endTime)
    {
        return [
            'start' => Carbon::parse("$quizDate $startTime")->format('Y-m-d H:i:s'),
            'end' => Carbon::parse("$quizDate $endTime")->format('Y-m-d H:i:s'),
        ];
    }

    public function createQuiz(CreateQuizRequest $request)
    {
        $validated = $request->validated();
        $professorId = auth()->user()->id;

        // Check if the professor owns the course
        $course = Course::findOrFail($validated['course_id']);
        if ($course->ProfessorID !== $professorId) {
            return response()->json(['message' => 'You do not own this course'], 403);
        }

        // Ensure quiz date and time are in the future
        $quizDateTime = Carbon::parse("{$validated['quiz_date']} {$validated['start_time']}");
        if ($quizDateTime->isPast()) {
            return response()->json(['message' => 'Quiz date and time must be in the future'], 422);
        }

        // Check for overlapping quizzes in the same course
        $overlappingQuiz = Quiz::where('CourseID', $validated['course_id'])
            ->where(function ($query) use ($validated) {
                $query->whereBetween('StartTime', [$validated['start_time'], $validated['end_time']])
                    ->orWhereBetween('EndTime', [$validated['start_time'], $validated['end_time']]);
            })
            ->exists();

        if ($overlappingQuiz) {
            return response()->json(['message' => 'Another quiz is already scheduled during this time'], 422);
        }

        // Calculate the duration automatically from start and end time
        $startTime = Carbon::parse($validated['start_time']);
        $endTime = Carbon::parse($validated['end_time']);
        $duration = $endTime->diffInMinutes($startTime);

        // Check if the current time is within the specified lockdown period
        $lockdownEnabled = Carbon::now()->between($startTime, $endTime);

        // Convert start and end time to Y-m-d H:i:s format
        $dates = $this->formatQuizDateTime($validated['quiz_date'], $validated['start_time'], $validated['end_time']);


        try {
            $quiz = Quiz::create([
                'Title' => $validated['title'],
                'Description' => $validated['description'],
                'Duration' => $duration,
                'StartTime' => $dates['start'],
                'EndTime' => $dates['end'],
                'QuizDate' => $validated['quiz_date'],
                'LockdownEnabled' => $lockdownEnabled,
                'CourseID' => $validated['course_id'],
            ]);
            // *** Send notifications to students enrolled in the course ***
            $message = "New Quiz in {$course->CourseName}: {$quiz->Title} is scheduled on {$quiz->QuizDate} at {$validated['start_time']}.";
            NotificationService::sendToCourseStudents($validated['course_id'], $message, 'quiz');

            return response()->json(['message' => 'Quiz created successfully'], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Something went wrong', 'error' => $e->getMessage()], 500);
        }
    }
    public function updateQuiz(UpdateQuizRequest $request, $id)
    {
        $validated = $request->validated();
        $professorId = auth()->user()->id;

        try {
            $quiz = Quiz::findOrFail($id);

            // Check if the professor owns the course
            $course = Course::findOrFail($quiz->CourseID);
            if ($course->ProfessorID !== $professorId) {
                return response()->json(['message' => 'You do not own this course'], 403);
            }

            // Prepare fields to update
            $updates = [];
            $updatedFields = []; // Track updated fields for notifications

            if (isset($validated['title'])) {
                $updates['Title'] = $validated['title'];
                $updatedFields[] = 'Title';
            }

            if (isset($validated['description'])) {
                $updates['Description'] = $validated['description'];
                $updatedFields[] = 'Description';
            }

            if (isset($validated['quiz_date']) && isset($validated['start_time']) && isset($validated['end_time'])) {
                // Ensure quiz date and time are in the future
                $quizDateTime = Carbon::parse("{$validated['quiz_date']} {$validated['start_time']}");
                if ($quizDateTime->isPast()) {
                    return response()->json(['message' => 'Quiz date and time must be in the future'], 422);
                }

                // Check for overlapping quizzes in the same course
                $overlappingQuiz = Quiz::where('CourseID', $quiz->CourseID)
                    ->where('QuizID', '!=', $quiz->QuizID) // Exclude the current quiz
                    ->where(function ($query) use ($validated) {
                        $query->whereBetween('StartTime', [$validated['start_time'], $validated['end_time']])
                            ->orWhereBetween('EndTime', [$validated['start_time'], $validated['end_time']]);
                    })
                    ->exists();

                if ($overlappingQuiz) {
                    return response()->json(['message' => 'Another quiz is already scheduled during this time'], 422);
                }

                // Calculate the duration automatically from start and end time
                $startTime = Carbon::parse($validated['start_time']);
                $endTime = Carbon::parse($validated['end_time']);
                $duration = $endTime->diffInMinutes($startTime);

                $updates['Duration'] = $duration;
                $dates = $this->formatQuizDateTime($validated['quiz_date'], $validated['start_time'], $validated['end_time']);
                $updates['StartTime'] = $dates['start'];
                $updates['EndTime'] = $dates['end'];
                $updates['QuizDate'] = $validated['quiz_date'];

                $updatedFields[] = 'Date & Time';
            }

            if (isset($validated['course_id'])) {
                // Ensure the course is owned by the professor
                $course = Course::findOrFail($validated['course_id']);
                if ($course->ProfessorID !== $professorId) {
                    return response()->json(['message' => 'You do not own this course'], 403);
                }
                $updates['CourseID'] = $validated['course_id'];
                $updatedFields[] = 'Course';
            }

            // Update the quiz with the prepared fields
            $quiz->update($updates);

            // Send notification if any field was updated
            if (!empty($updatedFields)) {
                $updatedFieldsList = implode(', ', $updatedFields);
                $message = "The Quiz '{$quiz->Title}' has been updated. Changes include: {$updatedFieldsList}. Please review the new details.";
                NotificationService::sendToCourseStudents($quiz->CourseID, $message, 'quiz');
            }

            return response()->json(['message' => 'Quiz updated successfully', 'data' => $quiz], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Something went wrong', 'error' => $e->getMessage()], 500);
        }
    }

    public function getAllQuizzes()
    {
        try {
            $professorId = auth()->user()->id;

            $courses = Course::where('ProfessorID', $professorId)
                ->select('CourseID')
                ->get();

            // Get quizzes for those courses and include CourseName
            $quizzes = Quiz::join('courses', 'quizzes.CourseID', '=', 'courses.CourseID') // Join with courses table
                ->whereIn('quizzes.CourseID', $courses->pluck('CourseID')) // Explicit table reference
                ->select(
                    'quizzes.QuizID',
                    'quizzes.Title',
                    'quizzes.Description',
                    'quizzes.StartTime',
                    'quizzes.EndTime',
                    'quizzes.CourseID',
                    'quizzes.Duration',
                    'quizzes.QuizDate',
                    'courses.CourseName',
                    'courses.CourseCode'
                )
                ->get();

            return response()->json([
                'quizzes' => $quizzes
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Something went wrong', 'error' => $e->getMessage()], 500);
        }
    }
    public function getCourseQuizzes($courseId, Request $request)
    {
        try {
            $professorId = auth()->user()->id;

            // Validate the course existence and professor association
            $course = Course::where('CourseID', $courseId)
                ->where('ProfessorID', $professorId)
                ->firstOrFail();

            if (!$course) {
                return response()->json(['message' => 'Professor not part of the course'], 403);
            }

            // Start building the query
            $query = Quiz::where('CourseID', $courseId);

            // Add search if provided
            if ($request->has('search') && !empty($request->search)) {
                $query->where('Title', 'LIKE', '%' . $request->search . '%');
            }

            // Add sorting
            if ($request->has('sort_order')) {
                switch ($request->sort_order) {
                    case 'nearest':
                        $query->orderBy('StartTime', 'asc');
                        break;
                    case 'longest':
                        $query->orderBy('StartTime', 'desc');
                        break;
                    default:
                        $query->orderBy('StartTime', 'asc');
                }
            } else {
                $query->orderBy('StartTime', 'asc');
            }

            // Get paginated results
            $quizzes = $query->paginate(9);

            return response()->json([
                'quizzes' => $quizzes->items(),
                'pagination' => [
                    'current_page' => $quizzes->currentPage(),
                    'total_pages' => $quizzes->lastPage(),
                    'total_items' => $quizzes->total()
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Something went wrong', 'error' => $e->getMessage()], 500);
        }
    }
    public function getQuiz($id)
    {
        try {
            $quiz = Quiz::findOrFail($id);

            // Paginate questions and load answers
            $paginatedQuestions = $quiz->questions()->with('answers')->paginate(5);

            // Manually append paginated questions to the quiz object
            $quiz->questions = $paginatedQuestions->items(); // Extract current page items

            return response()->json([
                'quiz' => $quiz,
                'pagination' => [
                    'current_page' => $paginatedQuestions->currentPage(),
                    'total_pages' => $paginatedQuestions->lastPage(),
                    'per_page' => $paginatedQuestions->perPage(),
                    'total_items' => $paginatedQuestions->total()
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Something went wrong', 'error' => $e->getMessage()], 500);
        }
    }

    public function deleteQuiz($id)
    {
        $professorId = auth()->user()->id;
        try {
            $quiz = Quiz::findOrFail($id);

            // Check if the professor owns the course
            $course = Course::findOrFail($quiz->CourseID);
            if ($course->ProfessorID !== $professorId) {
                return response()->json(['message' => 'You do not own this course'], 403);
            }
            $quiz->delete();

            return response()->json(['message' => 'Quiz deleted successfully'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Something went wrong', 'error' => $e->getMessage()], 500);
        }
    }

    public function getStudentQuizzes()
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

    private function hasStudentStartedQuiz($studentId, $quizId)
    {
        return StudentQuiz::where('student_id', $studentId)->where('quiz_id', $quizId)->exists();
    }
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

            // Store the student's quiz result
            QuizResult::create([
                'Score' => $totalScore,
                'Percentage' => $percentage,
                'Passed' => $passed,
                'SubmittedAt' => now(),
                'StudentID' => $studentId,
                'QuizID' => $quiz->QuizID,
            ]);

            DB::commit();
            return response()->json([
                'status' => 200,
                'message' => 'Quiz submitted successfully',
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Something went wrong', 'error' => $e->getMessage()], 500);
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
}