<?php

namespace App\Http\Controllers;

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
}