<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdatePassword;
use App\Http\Requests\UpdateProfile;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
        $this->middleware('role:admin')->only('getAllUsers');
    }

    public function updateProfile(UpdateProfile $request)
    {
        $user = auth()->user();

        $user->update($request->only(['name', 'email', 'phone', 'address']));

        return response()->json([
            'message' => 'Profile updated successfully',
            'data' => $user
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

    public function updatePassword(UpdatePassword $request)
    {
        $user = auth()->user();

        if (!password_verify($request->current_password, $user->password)) {
            return response()->json(['message' => 'Current password is incorrect'], 401);
        }

        $user->password = $request->new_password;
        $user->save();

        return response()->json([
            'message' => 'Password updated successfully. Please log in again.'
        ]);
    }
}