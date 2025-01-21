<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdatePassword;
use App\Http\Requests\UpdateProfile;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
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
    public function uploadProfilePicture(Request $request)
    {
        $request->validate([
            'profile_picture' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        try {
            $user = auth()->user();

            // Delete the old profile picture if it exists
            if ($user->profile_picture) {
                Storage::delete($user->profile_picture);
            }

            // Store the new profile picture
            $filePath = $request->file('profile_picture')->store('profile_pictures');

            // Update the user's profile picture path
            $user->profile_picture = $filePath;
            $user->save();

            return response()->json([
                'message' => 'Profile picture uploaded successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to upload profile picture',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    public function deleteProfilePicture()
    {
        try {
            $user = auth()->user();

            // Check if the user has a profile picture
            if (!$user->profile_picture) {
                return response()->json([
                    'message' => 'No profile picture to delete',
                ], 404);
            }

            // Delete the profile picture from storage
            Storage::delete($user->profile_picture);

            // Remove the profile picture path from the user's record
            $user->profile_picture = null;
            $user->save();

            return response()->json([
                'message' => 'Profile picture deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete profile picture',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function suspendStudent(Request $request, $studentId)
    {
        $request->validate([
            'reason' => 'required|string|max:255',
        ]);

        $student = User::where('id', $studentId)->where('role', 'user')->first();

        if (!$student) {
            return response()->json(['message' => 'Student not found'], 404);
        }

        $student->update([
            'is_suspended' => true,
        ]);

        if ($student->is_suspended) {
            $student->suspensions()->create([
                'Reason' => $request->reason,
                'SuspendedAt' => now(),
            ]);

            return response()->json(['message' => 'Student suspended successfully'], 200);
        }

        return response()->json(['message' => 'Student suspended successfully'], 200);
    }

    public function unsuspendStudent($studentId)
    {
        $student = User::where('id', $studentId)->where('role', 'user')->first();

        if (!$student) {
            return response()->json(['message' => 'Student not found'], 404);
        }

        $student->update([
            'is_suspended' => false,
        ]);

        return response()->json(['message' => 'Student unsuspended successfully'], 200);
    }

    public function viewSuspendedStudents()
    {
        $suspendedStudents = User::where('role', 'user')
            ->where('is_suspended', true)
            ->with('suspensions')
            ->get();

        return response()->json(['data' => $suspendedStudents]);
    }
}