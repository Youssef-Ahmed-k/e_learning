<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CourseRegistration;
use App\Models\Course;

class CourseRegistrationController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
        $this->middleware('role:user');
    }

    public function registerCourse(Request $request)
    {
        $request->validate([
            'CourseID' => 'required|exists:courses,CourseID',
        ]);

        $studentID = auth()->id();
        $courseID = $request->CourseID;

        // Check if the user has already registered for the course
        $existingRegistration = CourseRegistration::where('StudentID', $studentID)
            ->where('CourseID', $courseID)
            ->exists();

        if ($existingRegistration) {
            return response()->json([
                'message' => 'You have already registered for this course'
            ], 400);
        }

        // Check if the student has reached the maximum course limit
        $registeredCoursesCount = CourseRegistration::where('StudentID', $studentID)->count();

        if ($registeredCoursesCount >= 3) {
            return response()->json([
                'message' => 'You have reached the maximum course limit'
            ], 400);
        }

        // Register the student for the course
        $registration = CourseRegistration::create([
            'StudentID' => $studentID,
            'CourseID' => $courseID,
        ]);

        return response()->json([
            'message' => 'Course registered successfully',
            'registration' => $registration
        ]);
    }

    public function unregisterCourse(Request $request)
    {
        $request->validate([
            'CourseID' => 'required|exists:courses,CourseID',
        ]);

        $studentID = auth()->id();
        $courseID = $request->CourseID;

        // Unregister the student from the course
        $registration = CourseRegistration::where('StudentID', $studentID)
            ->where('CourseID', $courseID)
            ->first();

        if (!$registration) {
            return response()->json([
                'message' => 'You are not registered for this course'
            ], 400);
        }

        $registration->delete();

        return response()->json([
            'message' => 'Course unregistered successfully'
        ]);
    }
}