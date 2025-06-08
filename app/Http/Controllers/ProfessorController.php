<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateCourseMaterialRequest;
use App\Models\Course;
use App\Models\Notification;
use App\Models\Material;
use App\Http\Requests\UploadCourseMaterialRequest;
use App\Models\CheatingLog;
use Illuminate\Support\Facades\Storage;
use App\Models\CourseRegistration;
use App\Models\QuizResult;
use App\Models\Quiz;
use App\Models\User;
use App\Models\CheatingScore;
use App\Models\Question;
use App\Models\StudentAnswer;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProfessorController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
        $this->middleware('role:professor');
    }

    private function handleFileUpload(Request $request, $type, $oldPath = null)
    {
        if ($oldPath) {
            Storage::disk('public')->delete($oldPath);
        }

        return $request->file($type)->store($type === 'file' ? 'course_materials' : 'course_videos', 'public');
    }

    // view all registered courses
    public function viewRegisteredCourses()
    {
        $courses = Course::with('courseRegistrations.student')
            ->where('ProfessorID', auth()->user()->id)
            ->get();

        return response()->json(['data' => $courses]);
    }
    public function uploadCourseMaterial(UploadCourseMaterialRequest  $request)
    {
        if ($request->hasFile('file') && $request->hasFile('video')) {
            return response()->json(['message' => 'You can only upload either a file or a video, not both.'], 400);
        }

        DB::beginTransaction();
        try {
            // Ensure the course belongs to the authenticated professor
            $course = Course::where('CourseID', $request->course_id)
                ->where('ProfessorID', auth()->id())
                ->first();

            if (!$course) {
                return response()->json([
                    'message' => 'You are not authorized to upload materials for this course.',
                ], 403);
            }

            // Validate material type and uploaded file
            if ($request->material_type === 'pdf' && !$request->hasFile('file')) {
                return response()->json(['message' => 'You must upload a file for PDF materials.'], 400);
            }

            if ($request->material_type === 'video' && !$request->hasFile('video')) {
                return response()->json(['message' => 'You must upload a video for video materials.'], 400);
            }

            $filePath = $request->hasFile('file') ? $this->handleFileUpload($request, 'file') : null;
            $videoPath = $request->hasFile('video') ? $this->handleFileUpload($request, 'video') : null;

            $material = Material::create([
                'Title' => $request->title,
                'Description' => $request->description,
                'FilePath' => $filePath,
                'VideoPath' => $videoPath,
                'MaterialType' => $request->material_type,
                'CourseID' => $request->course_id,
                'ProfessorID' => auth()->id(),
            ]);
            //  Send notifications to students enrolled in the course 
            NotificationService::sendToCourseStudents(
                $course->CourseID,
                "New course material uploaded in {$course->CourseName}: {$material->Title}.",
                'material'
            );

            DB::commit();
            return response()->json(['message' => 'Course material uploaded successfully', 'data' => $material], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Upload course material failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    public function deleteCourseMaterial($material_id)
    {
        try {
            // Validate material_id exists
            $material = Material::findOrFail($material_id);

            // Check if the authenticated professor is the owner of the material
            if ($material->ProfessorID !== auth()->id()) {
                return response()->json([
                    'message' => 'You are not authorized to delete this material.',
                ], 403);
            }

            // Delete the material file and video from storage
            if ($material->FilePath) {
                Storage::disk('public')->delete($material->FilePath);
            }
            if ($material->VideoPath) {
                Storage::disk('public')->delete($material->VideoPath);
            }

            // Delete the material record from the database
            $material->delete();

            return response()->json([
                'message' => 'Course material deleted successfully',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Something went wrong',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    public function updateCourseMaterial(UpdateCourseMaterialRequest $request, $material_id)
    {
        if ($request->hasFile('file') && $request->hasFile('video')) {
            return response()->json(['message' => 'You can only upload either a file or a video, not both.'], 400);
        }

        DB::beginTransaction();
        try {
            $material = Material::findOrFail($material_id);

            if ($material->ProfessorID !== auth()->id()) {
                return response()->json(['message' => 'You are not authorized to update this material.'], 403);
            }

            if ($request->material_type === 'pdf' && !$request->hasFile('file')) {
                return response()->json(['message' => 'You must upload a file for PDF materials.'], 400);
            }

            if ($request->material_type === 'video' && !$request->hasFile('video')) {
                return response()->json(['message' => 'You must upload a video for video materials.'], 400);
            }

            $updatedFields = [];

            if ($request->hasFile('file')) {
                $material->FilePath = $this->handleFileUpload($request, 'file', $material->FilePath);
                $material->VideoPath = null;
                $updatedFields[] = 'File';
            }

            if ($request->hasFile('video')) {
                $material->VideoPath = $this->handleFileUpload($request, 'video', $material->VideoPath);
                $material->FilePath = null;
                $updatedFields[] = 'Video';
            }

            if ($request->title && $request->title !== $material->Title) {
                $updatedFields[] = 'Title';
            }

            if ($request->description && $request->description !== $material->Description) {
                $updatedFields[] = 'Description';
            }

            if ($request->material_type && $request->material_type !== $material->MaterialType) {
                $updatedFields[] = 'Material Type';
            }

            $material->update([
                'Title' => $request->title ?? $material->Title,
                'Description' => $request->description ?? $material->Description,
                'MaterialType' => $request->material_type ?? $material->MaterialType,
            ]);

            DB::commit();

            // **Get course name**
            $course = Course::findOrFail($material->CourseID);
            $courseName = $course->CourseName;

            // Send notification if any field was updated
            if (!empty($updatedFields)) {
                $updatedFieldsList = implode(', ', $updatedFields);
                NotificationService::sendToCourseStudents(
                    $material->CourseID,
                    "The course material '{$material->Title}' in the course '{$courseName}' has been updated. Changes include: {$updatedFieldsList}. Please check the new content.",
                    'material'
                );
            }

            return response()->json(['message' => 'Course material updated successfully', 'data' => $material], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Update course material failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    public function getCourseMaterials($course_id)
    {
        try {
            // Ensure the authenticated professor is assigned to the course
            $course = Course::where('CourseID', $course_id)
                ->where('ProfessorID', auth()->id())
                ->first();

            if (!$course) {
                return response()->json(['message' => 'Unauthorized access to course materials'], 403);
            }

            $materials = Material::where('CourseID', $course_id)->get();

            // Add file and video URLs
            foreach ($materials as $material) {
                if ($material->FilePath) {
                    $material->FilePath = Storage::url($material->FilePath);
                }
                if ($material->VideoPath) {
                    $material->VideoPath = Storage::url($material->VideoPath);
                }
            }

            return response()->json(['data' => $materials], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Something went wrong',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    public function getCoursesWithResults()
    {
        try {
            $professor = auth()->user();
            $courses = $professor->courses;

            if ($courses->isEmpty()) {
                return response()->json(['message' => 'No courses found for this professor'], 404);
            }

            $studentData = [];

            // Collect student scores, courses, and quiz count
            $coursesData = $courses->map(function ($course) use (&$studentData) {
                $quizzes = $course->quizzes;

                $quizzesData = $quizzes->map(function ($quiz) use (&$studentData, $course) {
                    $quizResults = $quiz->quizResults->sortByDesc('Score');

                    // Ensure we return quiz data, even if there are no results
                    if ($quizResults->isEmpty()) {
                        return [
                            'quiz_id' => $quiz->QuizID,
                            'quiz_name' => $quiz->title, // Ensure you're using 'title' instead of 'QuizName'
                            'quiz_results' => [],
                        ];
                    }

                    $quizResults->each(function ($result) use (&$studentData, $course, $quiz) {
                        $student = $result->student;
                        if (!$student) return;

                        if (!isset($studentData[$student->id])) {
                            $studentData[$student->id] = [
                                'student_id' => $student->id,
                                'student_name' => $student->name,
                                'courses' => [],
                                'quizzes' => [],
                                'total_score' => 0,
                            ];
                        }

                        $studentData[$student->id]['courses'][$course->CourseID] = true;
                        $studentData[$student->id]['quizzes'][$quiz->QuizID] = true;
                        $studentData[$student->id]['total_score'] += $result->Score;
                    });

                    return [
                        'quiz_id' => $quiz->QuizID,
                        'quiz_name' => $quiz->Title, // Ensure you're using the correct column name
                        'quiz_results' => $quizResults->values()->toArray(),
                    ];
                })->filter(); // Remove null values

                return [
                    'course_id' => $course->CourseID,
                    'course_name' => $course->CourseName,
                    'course_code' => $course->CourseCode,
                    'quizzes' => $quizzesData->values(), // Ensure quizzes are properly formatted
                ];
            });

            // Convert courses & quizzes from array to count
            $studentList = collect($studentData)->map(function ($data) {
                return [
                    'student_id' => $data['student_id'],
                    'student_name' => $data['student_name'],
                    'courses' => count($data['courses']),
                    'quizzes' => count($data['quizzes']),
                    'points' => $data['total_score'],
                ];
            });

            // Rank students based on total points
            $rankedStudents = $studentList->sortByDesc('points')->values()->map(function ($student, $index) {
                return [
                    'rank' => $index + 1,
                    'student_name' => $student['student_name'],
                    'courses' => $student['courses'],
                    'quizzes' => $student['quizzes'],
                    'points' => $student['points'],
                ];
            });

            return response()->json([
                'courses' => $coursesData,
                'best_performers' => $rankedStudents,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while retrieving professor courses and results.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getHighCheatingScores($quizId)
    {
        try {
            // Ensure the quiz belongs to the authenticated professor
            $quiz = Quiz::where('QuizID', $quizId)
                ->whereHas('course', function ($query) {
                    $query->where('ProfessorID', auth()->id());
                })
                ->first();

            if (!$quiz) {
                return response()->json(['message' => 'Unauthorized access to this quiz'], 403);
            }

            // Retrieve cheating scores where the score is 100
            $cheatingScores = CheatingScore::where('quiz_id', $quizId)
                ->where('score', 100)
                ->with('student') // Load student details
                ->get();

            if ($cheatingScores->isEmpty()) {
                return response()->json(['message' => 'No students have a cheating score of 100 in this quiz'], 404);
            }

            // Format the response
            $students = $cheatingScores->map(function ($cheatingScore) {
                return [
                    'student_id' => $cheatingScore->student->id,
                    'student_name' => $cheatingScore->student->name,
                    'student_email' => $cheatingScore->student->email,
                    'cheating_score' => $cheatingScore->score,
                ];
            });

            return response()->json([
                'quiz_id' => $quizId,
                'quiz_title' => $quiz->Title,
                'students' => $students,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while retrieving high cheating scores.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getCheatingLogs($quizId, $studentId)
    {
        try {
            // Ensure the quiz belongs to the authenticated professor
            $quiz = Quiz::where('QuizID', $quizId)
                ->whereHas('course', function ($query) {
                    $query->where('ProfessorID', auth()->id());
                })
                ->first();

            if (!$quiz) {
                return response()->json(['message' => 'Unauthorized access to this quiz'], 403);
            }

            // Retrieve cheating logs for the specified student in the quiz
            $cheatingLogs = CheatingLog::where('QuizID', $quizId)
                ->where('StudentID', $studentId)
                ->with(['student' => function ($query) {
                    $query->select('id', 'name', 'email');
                }])
                ->get()
                ->map(function ($log) {
                    return [
                        'log_id' => $log->LogID,
                        'student_id' => $log->StudentID,
                        'student_name' => $log->student->name ?? 'N/A',
                        'student_email' => $log->student->email ?? 'N/A',
                        'suspicious_behavior' => $log->SuspiciousBehavior,
                        'image_path' => $log->image_path,
                        'detected_at' => $log->DetectedAt,
                        'is_reviewed' => $log->IsReviewed,
                    ];
                });

            if ($cheatingLogs->isEmpty()) {
                return response()->json(['message' => 'No cheating logs found for this student in this quiz'], 404);
            }

            return response()->json([
                "logs" => $cheatingLogs,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while retrieving cheating logs.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function resetCheatingScore(Request $request, $quizId, $studentId)
    {
        try {
            DB::beginTransaction();

            // Ensure quiz exists and belongs to the professor's course
            $quiz = Quiz::where('QuizID', $quizId)
                ->whereHas('course', function ($query) {
                    $query->where('ProfessorID', auth()->user()->id);
                })
                ->firstOrFail();

            // Ensure quiz result exists
            $quizResult = QuizResult::where('QuizID', $quizId)
                ->where('StudentID', $studentId)
                ->firstOrFail();

            // Update cheating score in QuizResult
            $quizResult->update([
                'CheatingScore' => 0,
            ]);

            // Update or create cheating score in CheatingScore table
            $cheatingScore = CheatingScore::where('student_id', $studentId)
                ->where('quiz_id', $quizId)
                ->first();

            if ($cheatingScore) {
                $cheatingScore->update(['score' => 0]);
            } else {
                CheatingScore::create([
                    'student_id' => $studentId,
                    'quiz_id' => $quizId,
                    'score' => 0,
                ]);
                Log::info("Created new CheatingScore record for student_id: {$studentId}, quiz_id: {$quizId}");
            }

            DB::commit();

            // Notify student
            $message = "Your result for quiz {$quiz->Title} has been updated by the professor.";
            NotificationService::sendNotification($studentId, $message);

            return response()->json([
                'status' => 200,
                'message' => 'Cheating score reset successfully',
                'quiz_result' => [
                    'quiz_id' => $quizId,
                    'student_id' => $studentId,
                    'score' => $quizResult->Score,
                    'percentage' => $quizResult->Percentage,
                    'passed' => $quizResult->Passed,
                    'cheating_score' => 0,
                ],
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error resetting cheating score for student_id: {$studentId}, quiz_id: {$quizId}, error: {$e->getMessage()}");
            return response()->json([
                'message' => 'Something went wrong',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getStudentAnswers(Request $request, $quizId, $studentId)
    {
        try {
            $perPage = $request->input('per_page', 5);
            $page = $request->input('page', 1);

            // Ensure quiz exists and belongs to professor's course
            $quiz = Quiz::where('QuizID', $quizId)
                ->whereHas('course', function ($query) {
                    $query->where('ProfessorID', auth()->user()->id);
                })
                ->firstOrFail();

            // Paginate questions
            $questions = Question::where('QuizID', $quizId)
                ->with('answers')
                ->paginate($perPage, ['*'], 'page', $page);

            // Fetch student answers for paginated questions
            $studentAnswers = StudentAnswer::whereIn('QuestionID', $questions->pluck('QuestionID')->toArray())
                ->where('StudentID', $studentId)
                ->get()
                ->keyBy('QuestionID');

            // Prepare response
            $questionsData = $questions->map(function ($question) use ($studentAnswers) {
                $correctAnswer = $question->answers->firstWhere('IsCorrect', true);
                $studentAnswer = $studentAnswers->get($question->QuestionID);

                return [
                    'question_id' => $question->QuestionID,
                    'question_text' => $question->Content,
                    'marks' => $question->Marks ?? 5,
                    'image' => $question->Image ? Storage::disk('public')->url($question->Image) : null,
                    'possible_answers' => $question->answers->pluck('AnswerText')->toArray(),
                    'correct_answer' => $correctAnswer ? $correctAnswer->AnswerText : null,
                    'answers' => $question->answers->map(function ($answer) use ($studentAnswer, $correctAnswer) {
                        return [
                            'answer_id' => $answer->AnswerID,
                            'answer_text' => $answer->AnswerText,
                            'is_correct' => $answer->AnswerID == ($correctAnswer ? $correctAnswer->AnswerID : null),
                            'is_student_choice' => optional($studentAnswer)->SelectedAnswerID == $answer->AnswerID,
                        ];
                    }),
                ];
            })->values();

            $studentAnswersData = $studentAnswers->map(function ($studentAnswer) {
                $answer = $studentAnswer->answer; // Keep for compatibility
                return [
                    'question_id' => $studentAnswer->QuestionID,
                    'selected_answer' => $answer ? $answer->AnswerText : null,
                    'is_correct' => $answer ? $answer->IsCorrect : false,
                ];
            })->values();

            return response()->json([
                'status' => 200,
                'quiz' => [
                    'Title' => $quiz->Title,
                    'Description' => $quiz->Description,
                    'QuizDate' => $quiz->QuizDate,
                    'Duration' => $quiz->Duration,
                    'StartTime' => $quiz->StartTime,
                    'EndTime' => $quiz->EndTime,
                    'TotalMarks' => $questionsData->sum('marks'),
                ],
                'student_answers' => $studentAnswersData->toArray(),
                'correct_answers' => $questionsData->toArray(),
                'pagination' => [
                    'current_page' => $questions->currentPage(),
                    'total_pages' => $questions->lastPage(),
                    'total_items' => $questions->total(),
                    'per_page' => $questions->perPage(),
                ],
            ], 200);
        } catch (\Exception $e) {
            Log::error("Error fetching student answers for student_id: {$studentId}, quiz_id: {$quizId}, error: {$e->getMessage()}");
            return response()->json([
                'status' => 500,
                'message' => 'Something went wrong',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
