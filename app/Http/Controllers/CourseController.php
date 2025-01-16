<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseRegistration;
use Illuminate\Http\Request;

class CourseController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
        //$this->middleware('role:professor');
    }

    // view all students registered for a specific course
    public function getStudentsInCourse($courseID)
    {
        $students = CourseRegistration::with('student')
            ->where('CourseID', $courseID)
            ->get();

        return response()->json(['students' => $students]);
    }

    // get course details with professor name and materials
    public function getCourseDetails($courseID)
    {
        $courses = Course::with('professor', 'materials')
            ->where('CourseID', $courseID)
            ->first();

        if (!$courses) {
            return response()->json(['message' => 'Course not found'], 404);
        }

        return response()->json(['courses' => $courses]);
    }
}