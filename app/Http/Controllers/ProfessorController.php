<?php

namespace App\Http\Controllers;

use App\Models\Course;

use App\Models\Material;
use App\Http\Requests\UploadMaterial;
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
}

