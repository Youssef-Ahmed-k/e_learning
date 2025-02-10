<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateCourseMaterialRequest;
use App\Models\Course;
use App\Models\Notification;
use App\Models\Material;
use App\Http\Requests\UploadCourseMaterialRequest;
use Illuminate\Support\Facades\Storage;
use App\Models\CourseRegistration;
use App\Models\QuizResult;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
                'New course material uploaded in {$course->CourseName}: {$material->Title}.'
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
                    "The course material '{$material->Title}' in the course '{$courseName}' has been updated. Changes include: {$updatedFieldsList}. Please check the new content."
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
            // Get the authenticated professor user from the token
            $professor = auth()->user(); // هنا نجيب كائن الـ user نفسه مش الـ id فقط

            // Retrieve all courses that the professor is teaching
            $courses = $professor->courses; // نستخدم العلاقة hasMany بين الـ User و الـ Course

            if ($courses->isEmpty()) {
                return response()->json(['message' => 'No courses found for this professor'], 404);
            }

            // Map each course with its quizzes and student results
            $coursesData = $courses->map(function ($course) {
                // Get quizzes for the current course
                $quizzes = $course->quizzes; // العلاقة بين الـ Course و الـ Quiz

                // Map each quiz with its student results
                $quizzesData = $quizzes->map(function ($quiz) {
                    // Get quiz results for the specific quiz
                    $quizResults = $quiz->quizResults; // العلاقة بين الـ Quiz و الـ QuizResult

                    // Map each student's result
                    $studentsScores = $quizResults->map(function ($result) {
                        $student = $result->student; // الوصول إلى الطالب من خلال العلاقة بين QuizResult و User
                        return [
                            'student_name' => $student ? $student->name : 'Unknown',
                            'score' => $result->Score,
                            'percentage' => $result->Percentage,
                            'passed' => $result->Passed,
                        ];
                    });

                    return [
                        'quiz_id' => $quiz->QuizID,
                        'quiz_name' => $quiz->Title,
                        'students_scores' => $studentsScores,
                    ];
                });

                return [
                    'course_id' => $course->CourseID,
                    'course_name' => $course->CourseName,
                    'quizzes' => $quizzesData,
                ];
            });

            // Return courses, quizzes, and student results
            return response()->json([
                'courses' => $coursesData,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while retrieving professor courses and results.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}