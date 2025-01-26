<?php

namespace App\Http\Controllers;


use App\Http\Requests\CreateQuizRequest;
use App\Http\Requests\AddQuestionRequest;
use App\Http\Requests\UpdateQuizRequest;
use App\Models\Quiz;
use App\Models\Question;
use App\Models\Answer;
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
    public function updateQuiz(UpdateQuizRequest $request, $id)
    {
        $validated = $request->validated();

        // Calculate the duration automatically from start and end time
        $startTime = strtotime($validated['start_time']);
        $endTime = strtotime($validated['end_time']);
        $duration = ($endTime - $startTime) / 60; // Convert duration to minutes

        // Convert start and end time to Y-m-d H:i:s format
        $startDateTime = date('Y-m-d H:i:s', strtotime($validated['quiz_date'] . ' ' . $validated['start_time']));
        $endDateTime = date('Y-m-d H:i:s', strtotime($validated['quiz_date'] . ' ' . $validated['end_time']));

        try {
            $quiz = Quiz::findOrFail($id);
            $quiz->update([
                'Title' => $validated['title'],
                'Description' => $validated['description'],
                'Duration' => $duration,
                'StartTime' => $startDateTime,
                'EndTime' => $endDateTime,
                'QuizDate' => $validated['quiz_date'],
                'LockdownEnabled' => false, // Default value, can be updated later if needed
                'CourseID' => $validated['course_id'],
            ]);

            return response()->json(['message' => 'Quiz updated successfully'], 200);
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
    public function addQuestion(AddQuestionRequest $request)
    {
        $validated = $request->validated();

        // Convert correct_option to boolean for true_false type
        if ($validated['type'] === 'true_false') {
            $validated['correct_option'] = filter_var(
                strtolower($validated['correct_option']),
                FILTER_VALIDATE_BOOLEAN,
                FILTER_NULL_ON_FAILURE
            );

            // Ensure the correct_option is a valid boolean
            if ($validated['correct_option'] === null) {
                return response()->json([
                    'message' => 'Invalid correct_option value for true/false question.',
                ], 422);
            }
        }

        DB::beginTransaction();
        try {
            $question = new Question([
                'Content' => $validated['content'],
                'Type' => $validated['type'],
                'Marks' => $validated['marks'],
                'QuizID' => $validated['quiz_id'],
            ]);

            if ($request->hasFile('image')) {
                $question->image = $request->file('image')->store('question_images');
            }

            $question->save();

            switch ($validated['type']) {
                case 'mcq':
                    foreach ($validated['options'] as $option) {
                        $answer = new Answer([
                            'AnswerText' => $option,
                            'IsCorrect' => $option === $validated['correct_option'],
                            'QuestionID' => $question->QuestionID,
                        ]);
                        $answer->save();
                    }
                    break;

                case 'true_false':
                    $answer = new Answer([
                        'AnswerText' => $validated['correct_option'] ? 'True' : 'False',
                        'IsCorrect' => true,
                        'QuestionID' => $question->QuestionID,
                    ]);
                    $answer->save();
                    break;

                case 'short_answer':
                    $answer = new Answer([
                        'AnswerText' => $validated['correct_option'],
                        'IsCorrect' => true,
                        'QuestionID' => $question->QuestionID,
                    ]);
                    $answer->save();
                    break;
            }

            DB::commit();

            return response()->json(['message' => 'Question added successfully', 'question' => $question], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Something went wrong', 'error' => $e->getMessage()], 500);
        }
    }
}
