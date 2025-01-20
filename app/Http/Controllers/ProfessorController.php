<?php

namespace App\Http\Controllers;

use App\Models\Course;

use App\Models\Material;
use App\Http\Requests\UploadMaterial;
use App\Http\Requests\UpdateMaterial;
use Illuminate\Support\Facades\Storage;
use App\Models\CourseRegistration;
use Illuminate\Http\Request;

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
    public function uploadCourseMaterial(UploadMaterial $request)
    {
        try {
            // Validate input fields
            $request->validate([
                'title' => 'required|string|max:255',
                'description' => 'nullable|string|max:255',
                'file' => 'nullable|file|mimes:pdf,docx,txt|max:10240',
                'video' => 'nullable|file|mimes:mp4|max:10240',
                'material_type' => 'required|string|max:50',
                'course_id' => 'required|integer|exists:courses,CourseID',
            ]);

            // Ensure only one file or video is provided if both are present
            if ($request->hasFile('file') && $request->hasFile('video')) {
                return response()->json([
                    'message' => 'You can only upload either a file or a video, not both.',
                ], 400);
            }

            // Store the file or video
            $filePath = null;
            $videoPath = null;

            if ($request->hasFile('file')) {
                $filePath = $request->file('file')->store('course_materials');
            }
            if ($request->hasFile('video')) {
                $videoPath = $request->file('video')->store('course_videos');
            }

            $material = Material::create([
                'Title' => $request->title,
                'Description' => $request->description,
                'FilePath' => $filePath,
                'VideoPath' => $videoPath,
                'MaterialType' => $request->material_type,
                'CourseID' => $request->course_id,
                'ProfessorID' => auth()->id(),
            ]);

            return response()->json([
                'message' => 'Course material uploaded successfully',
                'data' => [
                    'material' => $material,
                ]
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Something went wrong',
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
    public function updateCourseMaterial(Request $request, $material_id)
    {
        try {
            // Validate material_id exists
            $material = Material::findOrFail($material_id);

            // Check if the authenticated professor is the owner of the material
            if ($material->ProfessorID !== auth()->id()) {
                return response()->json([
                    'message' => 'You are not authorized to update this material.',
                ], 403);
            }

            // Validate input fields
            $request->validate([
                'title' => 'sometimes|string|max:255',
                'description' => 'sometimes|string|max:255',
                'file' => 'sometimes|file|mimes:pdf,docx,txt|max:10240',
                'video' => 'sometimes|file|mimes:mp4|max:10240',
                'material_type' => 'sometimes|string|max:50',
            ]);

            // Ensure only one file or video is provided if both are present
            if ($request->hasFile('file') && $request->hasFile('video')) {
                return response()->json([
                    'message' => 'You can only upload either a file or a video, not both.',
                ], 400);
            }

            // Update fields if present in the request
            if ($request->has('title')) {
                $material->Title = $request->title;
            }
            if ($request->has('description')) {
                $material->Description = $request->description;
            }
            if ($request->has('file')) {
                // Delete the old file from storage
                Storage::delete($material->FilePath);

                // Store the new file
                $filePath = $request->file('file')->store('course_materials');
                $material->FilePath = $filePath;

                // Remove the old video path if a new file is uploaded
                $material->VideoPath = null;
            }
            if ($request->has('video')) {
                // Delete the old video from storage
                Storage::delete($material->VideoPath);

                // Store the new video
                $videoPath = $request->file('video')->store('course_videos');
                $material->VideoPath = $videoPath;

                // Remove the old file path if a new video is uploaded
                $material->FilePath = null;
            }
            if ($request->has('material_type')) {
                $material->MaterialType = $request->material_type;
            }

            $material->save();

            return response()->json([
                'message' => 'Course material updated successfully',
                'data' => [
                    'material' => $material,
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Something went wrong',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}