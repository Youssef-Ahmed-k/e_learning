<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateQuizRequest;
use App\Http\Requests\UpdateQuizRequest;
use App\Models\Quiz;
use App\Models\Course;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Carbon\Carbon;

class QuizController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
        $this->middleware('role:professor');
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
}
