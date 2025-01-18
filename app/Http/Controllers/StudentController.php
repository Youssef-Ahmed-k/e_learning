<?php

namespace App\Http\Controllers;

use App\Models\Material;
use App\Models\Course;
use App\Models\CourseRegistration;
use Illuminate\Http\Request;

class StudentController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
        $this->middleware('role:user');
    }

    // Allows a student to view the courses they are registered for
    public function viewRegisteredCourses()
    {
        $studentID = auth()->id();
        $registeredCourses = CourseRegistration::with('course')
            ->where('StudentID', $studentID)
            ->get();

        return response()->json([
            'data' => [
                'registeredCourses' => $registeredCourses,
            ]
        ]);
    }

    // View course materials
    public function viewCourseMaterials($courseID)
    {
        try {
            // Validate the course ID directly from the URL parameter
            if (!Course::where('CourseID', $courseID)->exists()) {
                return response()->json([
                    'message' => 'Invalid course ID',
                ], 404);
            }

            // Fetch course materials
            $courseMaterials = Material::where('CourseID', $courseID)->get();

            return response()->json([
                'data' => [
                    'courseMaterials' => $courseMaterials,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Something went wrong',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
