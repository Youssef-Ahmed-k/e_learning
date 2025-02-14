<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateUserByAdminRequest;
use App\Http\Requests\Register;
use App\Http\Requests\UpdateProfile;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Course;
use App\Models\RecentActivity;

class AdminController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
        $this->middleware('role:admin');
    }


    public function createUserAccount(CreateUserByAdminRequest $request)
    {
        try {
            $data = $request->validated();
            // Ensure the role is set to 'user' if not provided
            $data['role'] = $data['role'] ?? 'user';
            $user = User::create($data);

            return response()->json([
                'message' => 'Account created successfully',
                'user' => [
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                ]
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create user account',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function deleteUserAccount(User $user)
    {
        try {
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

    public function updateUserAccount(UpdateProfile $request, User $user)
    {
        try {
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
    public function getAllUsers()
    {
        $user = auth()->user();

        $users = User::paginate(10);

        return response()->json([
            'data' => $users->items(),
            'pagination' => [
                'current_page' => $users->currentPage(),
                'total_pages' => $users->lastPage(),
                'total_items' => $users->total(),
            ]
        ]);
    }

    public function getAllStudents()
    {
        $students = User::where('role', 'user')
            ->with('courseRegistrations.course')
            ->paginate(10);

        return response()->json([
            'students' => $students->map(function ($student) {
                return [
                    'id' => $student->id,
                    'name' => $student->name,
                    'email' => $student->email,
                    'is_blocked' => $student->is_suspended,
                    'courses' => $student->courseRegistrations->map(function ($registration) {
                        return [
                            'id' => $registration->course->CourseID,
                            'name' => $registration->course->CourseName,
                        ];
                    }),
                ];
            }),
            'pagination' => [
                'current_page' => $students->currentPage(),
                'total_pages' => $students->lastPage(),
                'total_items' => $students->total(),
            ]
        ]);
    }

    public function getAllProfessors()
    {
        $professors = User::where('role', 'professor')
            ->with('courses')
            ->paginate(10);

        return response()->json([
            'professors' => $professors->map(function ($professor) {
                return [
                    'id' => $professor->id,
                    'name' => $professor->name,
                    'email' => $professor->email,
                    'is_blocked' => $professor->is_suspended,
                    'courses' => $professor->courses->map(function ($course) {
                        return [
                            'id' => $course->CourseID,
                            'name' => $course->CourseName,
                        ];
                    }),
                ];
            }),
            'pagination' => [
                'current_page' => $professors->currentPage(),
                'total_pages' => $professors->lastPage(),
                'total_items' => $professors->total(),
            ]
        ]);
    }

    public function getStatistics()
    {
        try {
            $totalUsers = User::count();
            $totalProfessors = User::where('role', 'professor')->count();
            $totalStudents = User::where('role', 'user')->count();
            $totalCourses = Course::count();

            return response()->json([
                'totalUsers' => $totalUsers,
                'totalProfessors' => $totalProfessors,
                'totalStudents' => $totalStudents,
                'totalCourses' => $totalCourses,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Something went wrong',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getRecentActivities()
    {
        try {
            $activities = RecentActivity::with('user')
                ->orderBy('created_at', 'desc')
                ->take(10)
                ->get();

            return response()->json([
                'activities' => $activities,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Something went wrong',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
