<?php

namespace App\Http\Controllers;

use App\Http\Requests\Register;
use App\Http\Requests\UpdateProfile;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Course;

class AdminController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
        $this->middleware('role:admin');
    }


    public function createUserAccount(Register $request)
    {
        try {
            $data = $request->validated();
            $user = User::create($data);

            return response()->json([
                'message' => 'Account created successfully',
                'user' => [
                    'name' => $user->name,
                    'email' => $user->email,
                ]
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create user account',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function deleteUserAccount(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        try {
            $user = User::findOrFail($request->user_id);
            $user->delete();

            return response()->json([
                'message' => 'Account deleted successfully',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Something went wrong',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function updateUserAccount(UpdateProfile $request)
    {
        try {
            $user = User::findOrFail($request->user_id);

            if ($request->has('name')) {
                $user->name = $request->name;
            }
            if ($request->has('email')) {
                $user->email = $request->email;
            }
            if ($request->has('phone')) {
                $user->phone = $request->phone;
            }
            if ($request->has('address')) {
                $user->address = $request->address;
            }
            $user->save();

            return response()->json([
                'message' => 'Account updated successfully',
                'user' => [
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'address' => $user->address,
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Something went wrong',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    public function assignRole(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'role' => 'required|in:admin,user,professor'
        ]);


        $user = User::find($request->user_id);

        $user->role = $request->role;
        $user->save();

        return response()->json([
            'message' => 'Role assigned successfully',
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ]
        ]);
    }

    public function getAllStudents()
    {
        $students = User::where('role', 'user')->paginate(10);

        return response()->json([
            'students' => $students->items(),
            'pagination' => [
                'current_page' => $students->currentPage(),
                'total_pages' => $students->lastPage(),
                'total_items' => $students->total(),
            ]
        ]);
    }

    public function getAllProfessors()
    {
        $professors = User::where('role', 'professor')->paginate(10);

        return response()->json([
            'professors' => $professors->items(),
            'pagination' => [
                'current_page' => $professors->currentPage(),
                'total_pages' => $professors->lastPage(),
                'total_items' => $professors->total(),
            ]
        ]);
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
            'course' => $course
        ]);
    }

    public function updateCourse(Request $request)
    {
        $request->validate([
            'CourseID' => 'required|exists:courses,CourseID',
            'CourseName' => 'required|string|max:255',
        ]);

        $course = Course::find($request->CourseID);

        if (!$course) {
            return response()->json([
                'message' => 'Course not found'
            ], 404);
        }

        $course->CourseName = $request->CourseName;
        $course->save();

        return response()->json([
            'message' => 'Course updated successfully',
            'course' => $course
        ]);
    }

    public function deleteCourse(Request $request)
    {
        $request->validate([
            'CourseID' => 'required|exists:courses,CourseID',
        ]);

        $course = Course::find($request->CourseID);

        if (!$course) {
            return response()->json([
                'message' => 'Course not found'
            ], 404);
        }

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
}
