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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class QuizController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
        $this->middleware('role:professor', ['except' => ['getStudentQuizzes', 'startQuiz'  ,'submitQuiz']]);
        $this->middleware('role:user', ['only' => ['getStudentQuizzes', 'startQuiz','submitQuiz']]);
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

            if (isset($validated['title'])) {
                $updates['Title'] = $validated['title'];
            }

            if (isset($validated['description'])) {
                $updates['Description'] = $validated['description'];
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
            }

            if (isset($validated['course_id'])) {
                // Ensure the course is owned by the professor
                $course = Course::findOrFail($validated['course_id']);
                if ($course->ProfessorID !== $professorId) {
                    return response()->json(['message' => 'You do not own this course'], 403);
                }
                $updates['CourseID'] = $validated['course_id'];
            }

            // Update the quiz with the prepared fields
            $quiz->update($updates);

            return response()->json(['message' => 'Quiz updated successfully', 'data' => $quiz], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Something went wrong', 'error' => $e->getMessage()], 500);
        }
    }
    public function getCourseQuizzes($courseId)
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

            // Get quizzes created by the professor in the course
            $quizzes = Quiz::where('CourseID', $courseId)
                ->paginate(10);

            return response()->json([
                'quizzes' => $quizzes->items(),
                'pagination' =>
                [
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
            $quiz = Quiz::with('questions.answers')->findOrFail($id);

            return response()->json(['quiz' => $quiz], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Something went wrong', 'error' => $e->getMessage()], 500);
        }
    }

    public function deleteQuiz($id)
    {
        try {
            $quiz = Quiz::findOrFail($id);
            $quiz->delete();

            return response()->json(['message' => 'Quiz deleted successfully'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Something went wrong', 'error' => $e->getMessage()], 500);
        }
    }
    public function getStudentQuizzes()
    {
        try {
            $studentId = auth()->user()->id;

            // Get courses the student is enrolled in
            $courses = CourseRegistration::where('StudentID', $studentId)->pluck('CourseID');

            // Get quizzes for the courses the student is enrolled in
            $quizzes = Quiz::whereIn('CourseID', $courses)
                ->select('QuizID', 'Title', 'Description', 'StartTime', 'EndTime', 'CourseID', 'Duration', 'QuizDate')
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

            // Get current time
            $currentDateTime = Carbon::now();
            $startTime = Carbon::parse($quiz->StartTime);
            $endTime = Carbon::parse($quiz->EndTime);

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

            // Check if the student has already started the quiz
            $studentId = auth()->user()->id;
            if ($this->hasStudentStartedQuiz($studentId, $id)) {
                return response()->json(['message' => 'You have already started this quiz'], 403);
            }

            // Record that the student has started the quiz
            StudentQuiz::create([
                'student_id' => $studentId,
                'quiz_id' => $id,
            ]);

            return response()->json(['quiz' => $quiz], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Something went wrong', 'error' => $e->getMessage()], 500);
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
            'answers.*.answer' => 'required', // Ensure each answer is provided
        ]);

        try {
            DB::beginTransaction(); 

            $quiz = Quiz::findOrFail($quizId); // Ensure the quiz exists

            // Loop through each answer provided by the student
            foreach ($validated['answers'] as $answerData) {
                $question = Question::findOrFail($answerData['question_id']); // Ensure the question exists

                // Store student's answer in the database
                StudentAnswer::create([
                    'StudentId' => auth()->user()->id, 
                    'QuestionId' => $question->QuestionID, 
                    'SelectedAnswerId' => $answerData['answer'], 
                    'QuizID' => $quiz->QuizID, 
                ]);
            }

            DB::commit(); 
            return response()->json(['message' => 'Quiz submitted successfully'], 200);
        } catch (\Exception $e) {
            DB::rollBack(); // Rollback the transaction if an error occurs
            return response()->json(['message' => 'Something went wrong', 'error' => $e->getMessage()], 500);
        }
    }
}