<?php

namespace App\Http\Controllers;


use App\Http\Requests\CreateQuizRequest;
use App\Http\Requests\AddQuestionRequest;
use App\Http\Requests\UpdateQuizRequest;
use App\Http\Requests\UpdateQuestionRequest;
use App\Models\Quiz;
use App\Models\Question;
use App\Models\Answer;
use App\Models\Course;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

        try {
            $quiz = Quiz::findOrFail($id);

            // Prepare fields to update
            $updates = [];

            if (isset($validated['title'])) {
                $updates['Title'] = $validated['title'];
            }

            if (isset($validated['description'])) {
                $updates['Description'] = $validated['description'];
            }

            if (isset($validated['quiz_date']) && isset($validated['start_time']) && isset($validated['end_time'])) {
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

            // Check if the professor is part of the course
            $course = Course::where('CourseID', $courseId)
                ->where('ProfessorID', $professorId)
                ->first();

            if (!$course) {
                return response()->json(['message' => 'Professor not part of the course'], 403);
            }

            // Get quizzes created by the professor in the course
            $quizzes = Quiz::where('CourseID', $courseId)
                ->pluck('Title');

            return response()->json(['quizzes' => $quizzes], 200);
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

            $this->saveAnswers($validated, $question);

            DB::commit();

            return response()->json(['message' => 'Question added successfully', 'question' => $question], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Something went wrong', 'error' => $e->getMessage()], 500);
        }
    }

    public function updateQuestion(UpdateQuestionRequest $request, $id)
    {
        $validated = $request->validated();

        // Convert correct_option to boolean for true_false type
        if (isset($validated['type']) && $validated['type'] === 'true_false') {
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
            $question = Question::findOrFail($id);

            // Prepare fields to update
            $updates = [];

            if (isset($validated['content'])) {
                $updates['Content'] = $validated['content'];
            }

            if (isset($validated['type'])) {
                $updates['Type'] = $validated['type'];
            }

            if (isset($validated['marks'])) {
                $updates['Marks'] = $validated['marks'];
            }

            if (isset($validated['quiz_id'])) {
                $updates['QuizID'] = $validated['quiz_id'];
            }

            if ($request->hasFile('image')) {
                $updates['image'] = $request->file('image')->store('question_images');
            }

            $question->update($updates);

            // Update the answers if necessary
            if (isset($validated['correct_option']) || isset($validated['options'])) {
                $this->updateAnswers($validated, $question);
            }

            DB::commit();

            return response()->json(['message' => 'Question updated successfully', 'question' => $question], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Something went wrong', 'error' => $e->getMessage()], 500);
        }
    }

    public function deleteQuestion($id)
    {
        try {
            DB::beginTransaction();

            $question = Question::findOrFail($id);

            // Delete related answers
            Answer::where('QuestionID', $question->QuestionID)->delete();

            // Delete the question
            $question->delete();

            DB::commit();

            return response()->json(['message' => 'Question deleted successfully'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Something went wrong', 'error' => $e->getMessage()], 500);
        }
    }

    protected function saveAnswers($validated, $question)
    {
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
    }

    protected function updateAnswers($validated, $question)
    {
        // Delete existing answers
        Answer::where('QuestionID', $question->QuestionID)->delete();

        // Save new answers
        $this->saveAnswers($validated, $question);
    }
}