<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class QuestionController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
        $this->middleware('role:professor');
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