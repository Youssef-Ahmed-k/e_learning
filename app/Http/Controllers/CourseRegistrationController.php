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

    public function registerCourses(Request $request)
    {
        $request->validate([
            'CourseIDs' => 'required|array|min:1',
            'CourseIDs.*' => 'required|exists:courses,CourseID',
        ]);

        $studentID = auth()->id();
        $courseIDs = $request->CourseIDs;

        // Get already registered courses
        $existingRegistrations = CourseRegistration::where('StudentID', $studentID)
            ->whereIn('CourseID', $courseIDs)
            ->pluck('CourseID')
            ->toArray();

        // Check how many courses the student has already registered for
        $registeredCoursesCount = CourseRegistration::where('StudentID', $studentID)->count();

        // Filter courses that can be registered
        $validCourses = array_diff($courseIDs, $existingRegistrations);

        // Ensure the student does not exceed the maximum allowed courses
        $availableSlots = max(0, 3 - $registeredCoursesCount);
        $coursesToRegister = array_slice($validCourses, 0, $availableSlots);

        if (empty($coursesToRegister)) {
            return response()->json([
                'message' => 'You have reached the maximum course limit or are already registered for these courses.'
            ], 400);
        }

        // Register the student for valid courses
        $registrations = [];
        foreach ($coursesToRegister as $courseID) {
            $registrations[] = [
                'StudentID' => $studentID,
                'CourseID' => $courseID,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        CourseRegistration::insert($registrations);

        return response()->json([
            'message' => 'Courses registered successfully',
            'registered_courses' => $coursesToRegister
        ]);
    }

    public function unregisterCourses(Request $request)
{
    $request->validate([
        'CourseIDs' => 'required|array|min:1',
        'CourseIDs.*' => 'required|exists:courses,CourseID',
    ]);

    $studentID = auth()->id();
    $courseIDs = $request->CourseIDs;

    // Unregister the student from the courses
    $deletedCount = CourseRegistration::where('StudentID', $studentID)
        ->whereIn('CourseID', $courseIDs)
        ->delete();

    if ($deletedCount === 0) {
        return response()->json([
            'message' => 'No matching course registrations found'
        ], 400);
    }

    return response()->json([
        'message' => 'Courses unregistered successfully'
    ]);
}
}