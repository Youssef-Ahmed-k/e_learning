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
}