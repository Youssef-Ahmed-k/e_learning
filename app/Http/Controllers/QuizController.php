<?php

namespace App\Http\Controllers;


use App\Http\Requests\CreateQuizRequest;
use App\Http\Requests\AddQuestionRequest;
use App\Models\Quiz;
use App\Models\Question;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class QuizController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
        $this->middleware('role:professor');
    }
    public function createQuiz(CreateQuizRequest $request)
    {
        $validated = $request->validated();

        // Calculate the duration automatically from start and end time
        $startTime = strtotime($validated['start_time']);
        $endTime = strtotime($validated['end_time']);
        $duration = ($endTime - $startTime) / 60; // Convert duration to minutes

        // Check if the current time is within the specified lockdown period
        $currentTime = time();
        $lockdownEnabled = $currentTime >= $startTime && $currentTime <= $endTime;

        // Convert start and end time to Y-m-d H:i:s format
         $startDateTime = date('Y-m-d H:i:s', strtotime($validated['quiz_date'] . ' ' . $validated['start_time']));
         $endDateTime = date('Y-m-d H:i:s', strtotime($validated['quiz_date'] . ' ' . $validated['end_time']));
        
        try {
            $quiz = Quiz::create([
                'Title' => $validated['title'],
                'Description' => $validated['description'],
                'Duration' => $duration,
                'StartTime' => $startDateTime,
                'EndTime' => $endDateTime,
                'QuizDate' => $validated['quiz_date'],
                'LockdownEnabled' => $lockdownEnabled,
                'CourseID' => $validated['course_id'],
            ]);

            return response()->json(['message' => 'Quiz created successfully'], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Something went wrong', 'error' => $e->getMessage()], 500);
        }
    }

}
