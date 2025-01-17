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

    // view all registered courses
    public function viewRegisteredCourses()
    {
        $courses = Course::with('courseRegistrations.student')
            ->where('ProfessorID', auth()->user()->id)
            ->get();

        return response()->json(['courses' => $courses]);
    }
    public function uploadCourseMaterial(UploadMaterial $request)
    {
        try {
            $filePath = $request->file('file')->store('course_materials');

            $material = Material::create([
                'Title' => $request->title,
                'Description' => $request->description,
                'FilePath' => $filePath,
                'MaterialType' => $request->material_type,
                'CourseID' => $request->course_id,
                'ProfessorID' => auth()->id(),
            ]);

            return response()->json([
                'message' => 'Course material uploaded successfully',
                'material' => $material,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Something went wrong',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    public function deleteCourseMaterial(Request $request)
    {
        $request->validate([
            'material_id' => 'required|exists:materials,MaterialID',
        ]);

        try {
            $material = Material::findOrFail($request->material_id);

            // Check if the authenticated professor is the owner of the material
            if ($material->ProfessorID !== auth()->id()) {
                return response()->json([
                    'message' => 'You are not authorized to delete this material.',
                ], 403);
            }

            // Delete the material file from storage
            Storage::delete($material->FilePath);

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
    public function updateCourseMaterial(UpdateMaterial $request)
    {

        try {
            $material = Material::findOrFail($request->material_id);

            // Check if the authenticated professor is the owner of the material
            if ($material->ProfessorID !== auth()->id()) {
                return response()->json([
                    'message' => 'You are not authorized to update this material.',
                ], 403);
            }

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
            }
            if ($request->has('material_type')) {
                $material->MaterialType = $request->material_type;
            }

            $material->save();

            return response()->json([
                'message' => 'Course material updated successfully',
                'material' => $material,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Something went wrong',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
