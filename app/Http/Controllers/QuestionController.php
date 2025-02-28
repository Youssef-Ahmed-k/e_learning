<?php

namespace App\Http\Controllers;

use App\Http\Requests\AddQuestionRequest;
use App\Http\Requests\UpdateQuestionRequest;
use App\Models\Answer;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\StudentAnswer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class QuestionController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
        $this->middleware('role:professor')->except('getQuizQuestions', 'compareStudentAnswers',);
        $this->middleware('role:user')->only('getQuizQuestions',  'compareStudentAnswers',);
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