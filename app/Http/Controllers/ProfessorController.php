<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateCourseMaterialRequest;
use App\Models\Course;

use App\Models\Material;
use App\Http\Requests\UploadCourseMaterialRequest;
use Illuminate\Support\Facades\Storage;
use App\Models\CourseRegistration;
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
            Storage::delete($oldPath);
        }

        return $request->file($type)->store($type === 'file' ? 'course_materials' : 'course_videos');
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
            Storage::delete($material->FilePath);
            Storage::delete($material->VideoPath);

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
    public function updateCourseMaterial(UpdateCourseMaterialRequest  $request, $material_id)
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

            if ($request->hasFile('file')) {
                $material->FilePath = $this->handleFileUpload($request, 'file', $material->FilePath);
                $material->VideoPath = null;
            }

            if ($request->hasFile('video')) {
                $material->VideoPath = $this->handleFileUpload($request, 'video', $material->VideoPath);
                $material->FilePath = null;
            }

            $material->update([
                'Title' => $request->title ?? $material->Title,
                'Description' => $request->description ?? $material->Description,
                'MaterialType' => $request->material_type ?? $material->MaterialType,
            ]);

            DB::commit();
            return response()->json(['message' => 'Course material updated successfully', 'data' => $material], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Update course material failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}