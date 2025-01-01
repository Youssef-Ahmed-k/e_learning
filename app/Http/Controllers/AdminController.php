<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;

class AdminController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
        $this->middleware('role:admin');
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
}
