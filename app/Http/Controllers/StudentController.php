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
            'registeredCourses' => $registeredCourses,
        ]);
    }

    // get all courses and their professors
    public function getAllCoursesWithProfessors()
    {
        $courses = Course::with('professor')->get();

        return response()->json([
            'courses' => $courses,
        ]);
    }
     // View course materials
     public function viewCourseMaterials(Request $request)
     {
         $request->validate([
             'course_id' => 'required|exists:courses,CourseID',
         ]);
 
         try {
             $courseID = $request->course_id;
 
             // Fetch course materials
             $courseMaterials = Material::where('CourseID', $courseID)->get();
 
             return response()->json([
                 'courseMaterials' => $courseMaterials,
             ]);
         } catch (\Exception $e) {
             return response()->json([
                 'message' => 'Something went wrong',
                 'error' => $e->getMessage(),
             ], 500);
         }
     }
}