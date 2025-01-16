<?php

namespace App\Http\Controllers;

use App\Models\Course;
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

    // view all students registered for a specific course
    public function getStudentsInCourse($courseID)
    {
        $students = CourseRegistration::with('student')
            ->where('CourseID', $courseID)
            ->get();

        return response()->json(['students' => $students]);
    }
}