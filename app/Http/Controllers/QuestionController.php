<?php

namespace App\Http\Controllers;

use App\Http\Requests\AddQuestionRequest;
use App\Http\Requests\UpdateQuestionRequest;
use App\Models\Answer;
use App\Models\Question;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class QuestionController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
        $this->middleware('role:professor')->except('getQuizQuestions');
        $this->middleware('role:user')->only('getQuizQuestions');
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

        // Validate correct_option for MCQ questions
        if ($validated['type'] === 'mcq' && !in_array($validated['correct_option'], $validated['options'])) {
            return response()->json([
                'message' => 'The correct_option must be one of the provided options for MCQ questions.',
            ], 422);
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
                $question->image = $request->file('image')->store('question_images', 'public');
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
            $correctOption = strtolower($validated['correct_option']);

            // Normalize true/false values
            if (in_array($correctOption, ['true', '1'], true)) {
                $validated['correct_option'] = true;
            } elseif (in_array($correctOption, ['false', '0'], true)) {
                $validated['correct_option'] = false;
            } else {
                return response()->json([
                    'message' => 'Invalid correct_option value for true/false question.',
                ], 422);
            }
        }

        // Validate correct_option for MCQ questions
        if (isset($validated['type']) && $validated['type'] === 'mcq' && !in_array($validated['correct_option'], $validated['options'])) {
            return response()->json([
                'message' => 'The correct_option must be one of the provided options for MCQ questions.',
            ], 422);
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

            // Recalculate total marks for the quiz
            $question->quiz->calculateTotalMarks();

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

    public function getQuizQuestions($id)
    {
        try {
            $perPage = 1;
            $questions = Question::with('answers')
                ->where('QuizID', $id)
                ->paginate($perPage);

            return response()->json([
                'questions' => $questions->items(),
                'pagination' => [
                    'current_page' => $questions->currentPage(),
                    'per_page' => $questions->perPage(),
                    'total' => $questions->total(),
                    'last_page' => $questions->lastPage(),
                    'next_page_url' => $questions->nextPageUrl(),
                    'prev_page_url' => $questions->previousPageUrl(),
                ],
            ], 200);
        } catch (\Exception $e) {
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
                $answers = [
                    [
                        'AnswerText' => 'True',
                        'IsCorrect' => $validated['correct_option'] === true || $validated['correct_option'] === 'true' || $validated['correct_option'] === 1,
                        'QuestionID' => $question->QuestionID,
                    ],
                    [
                        'AnswerText' => 'False',
                        'IsCorrect' => $validated['correct_option'] === false || $validated['correct_option'] === 'false' || $validated['correct_option'] === 0,
                        'QuestionID' => $question->QuestionID,
                    ],
                ];

                // Save both answers
                foreach ($answers as $answerData) {
                    $answer = new Answer($answerData);
                    $answer->save();
                }
                break;
        }
    }

    protected function updateAnswers($validated, $question)
    {
        switch ($validated['type']) {
            case 'mcq':
                // Get existing answers
                $existingAnswers = Answer::where('QuestionID', $question->QuestionID)->get();
                $existingCount = $existingAnswers->count();
                $newCount = count($validated['options']);

                // Update existing answers
                foreach ($validated['options'] as $index => $option) {
                    if ($index < $existingCount) {
                        // Update existing answer
                        $existingAnswers[$index]->update([
                            'AnswerText' => $option,
                            'IsCorrect' => $option === $validated['correct_option']
                        ]);
                    } else {
                        // Create new answer if we have more options than before
                        Answer::create([
                            'AnswerText' => $option,
                            'IsCorrect' => $option === $validated['correct_option'],
                            'QuestionID' => $question->QuestionID
                        ]);
                    }
                }

                // Delete extra answers if we have fewer options than before
                if ($newCount < $existingCount) {
                    Answer::where('QuestionID', $question->QuestionID)
                        ->orderBy('id', 'desc')
                        ->limit($existingCount - $newCount)
                        ->delete();
                }
                break;

            case 'true_false':
                $existingAnswers = Answer::where('QuestionID', $question->QuestionID)->get();

                // Update or create "True" answer
                if ($existingAnswers->count() > 0) {
                    $existingAnswers[0]->update([
                        'AnswerText' => 'True',
                        'IsCorrect' => $validated['correct_option'] === true ||
                            $validated['correct_option'] === 'true' ||
                            $validated['correct_option'] === 1
                    ]);
                } else {
                    Answer::create([
                        'AnswerText' => 'True',
                        'IsCorrect' => $validated['correct_option'] === true ||
                            $validated['correct_option'] === 'true' ||
                            $validated['correct_option'] === 1,
                        'QuestionID' => $question->QuestionID
                    ]);
                }

                // Update or create "False" answer
                if ($existingAnswers->count() > 1) {
                    $existingAnswers[1]->update([
                        'AnswerText' => 'False',
                        'IsCorrect' => $validated['correct_option'] === false ||
                            $validated['correct_option'] === 'false' ||
                            $validated['correct_option'] === 0
                    ]);
                } else {
                    Answer::create([
                        'AnswerText' => 'False',
                        'IsCorrect' => $validated['correct_option'] === false ||
                            $validated['correct_option'] === 'false' ||
                            $validated['correct_option'] === 0,
                        'QuestionID' => $question->QuestionID
                    ]);
                }
                break;
        }
    }
}
