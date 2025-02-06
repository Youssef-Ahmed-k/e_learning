<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseRegistration;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CourseController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
        $this->middleware('role:admin')->except('getAllCoursesWithProfessors', 'getStudentsInCourse');
        $this->middleware('role:user')->only('getAllCoursesWithProfessors');
        $this->middleware('role:professor')->only('getStudentsInCourse');
    }

    // view all students registered for a specific course
    public function getStudentsInCourse($courseID)
    {
        $students = CourseRegistration::with('student')
            ->where('CourseID', $courseID)
            ->paginate(10);

        return response()->json([
            'data' => $students->items(),
            'pagination' => [
                "current_page" => $students->currentPage(),
                "total_pages" => $students->lastPage(),
                "total_items" => $students->total(),
            ]
        ]);
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
            'CourseCode' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-zA-Z0-9]+$/', // Alphanumeric validation
                Rule::unique('courses', 'CourseCode'),
            ],
        ], [
            'CourseCode.unique' => 'The course code must be unique.',
            'CourseCode.regex' => 'The course code must contain only letters and numbers.',
        ]);

        $course = Course::create([
            'CourseName' => $request->CourseName,
            'CourseCode' => $request->CourseCode
        ]);

        return response()->json([
            'message' => 'Course created successfully',
            'data' => $course
        ]);
    }

    public function updateCourse(Request $request, Course $course)
    {
        $request->validate([
            'CourseName' => 'nullable|string|max:255',
            'CourseCode' => [
                'nullable',
                'string',
                'max:255',
                'regex:/^[a-zA-Z0-9]+$/', // Alphanumeric validation
                Rule::unique('courses', 'CourseCode')->ignore($course->id),
            ],
        ], [
            'CourseCode.unique' => 'The course code must be unique.',
            'CourseCode.regex' => 'The course code must contain only letters and numbers.',
        ]);

        if ($request->has('CourseName')) {
            $course->CourseName = $request->CourseName;
        }

        if ($request->has('CourseCode')) {
            $course->CourseCode = $request->CourseCode;
        }
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

    public function getAllCoursesWithProfessorsForAdmin()
    {
        $courses = Course::with('professor')
            ->withCount('courseRegistrations')
            ->paginate(10);


        return response()->json([
            'data' => $courses->items(),
            'pagination' => [
                "current_page" => $courses->currentPage(),
                "total_pages" => $courses->lastPage(),
                "total_items" => $courses->total(),
            ]
        ]);
    }
}
