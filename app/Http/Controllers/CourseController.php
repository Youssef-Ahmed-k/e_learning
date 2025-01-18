<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseRegistration;
use App\Models\User;
use Illuminate\Http\Request;

class CourseController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
        $this->middleware('role:admin');
        $this->middleware('role:user,admin')->only('getAllCoursesWithProfessors');
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

        return response()->json(['data' => $courses]);
    }

    public function createCourse(Request $request)
    {
        $request->validate([
            'CourseName' => 'required|string|max:255',
        ]);

        $course = Course::create([
            'CourseName' => $request->CourseName,
        ]);

        return response()->json([
            'message' => 'Course created successfully',
            'data' => $course
        ]);
    }

    public function updateCourse(Request $request, Course $course)
    {
        $request->validate([
            'CourseName' => 'required|string|max:255',
        ]);

        $course->CourseName = $request->CourseName;
        $course->save();

        return response()->json([
            'message' => 'Course updated successfully',
            'data' => $course
        ]);
    }

    public function deleteCourse(Course $course)
    {
        $course->delete();

        return response()->json([
            'message' => 'Course deleted successfully'
        ]);
    }

    public function getAllCourses()
    {
        $courses = Course::paginate(10);

        return response()->json([
            'courses' => $courses->items(),
            'pagination' => [
                "current_page" => $courses->currentPage(),
                "total_pages" => $courses->lastPage(),
                "total_items" => $courses->total(),
            ]
        ]);
    }

    public function assignCourseToProfessor(Request $request)
    {
        $request->validate([
            'CourseID' => 'required|exists:courses,CourseID',
            'ProfessorID' => 'required|exists:users,id',
        ]);

        // Ensure the professor exists and is of the correct role
        $professor = User::where('id', $request->ProfessorID)->where('role', 'professor')->first();


        if (!$professor) {
            return response()->json([
                'message' => 'The selected user is not a professor'
            ], 404);
        }

        $course = Course::find($request->CourseID);

        if (!$course) {
            return response()->json([
                'message' => 'Course not found'
            ], 404);
        }

        $course->ProfessorID = $professor->id;
        $course->save();

        return response()->json([
            'message' => 'Course assigned successfully',
            'course' => [
                'id' => $course->CourseID,
                'name' => $course->CourseName,
                'professor' => [
                    'id' => $professor->id,
                    'name' => $professor->name,
                ],
            ],
        ]);
    }

    // get all courses and their professors
    public function getAllCoursesWithProfessors()
    {
        $courses = Course::with('professor')->get();

        return response()->json([
            'data' => [
                'courses' => $courses,
            ]
        ]);
    }
}