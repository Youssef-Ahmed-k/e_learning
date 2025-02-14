<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdatePassword;
use App\Http\Requests\UpdateProfile;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
        $this->middleware('role:admin,professor')->only('suspendStudent', 'unsuspendStudent', 'viewSuspendedStudents');
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
                Storage::disk('public')->delete($user->profile_picture);
            }

            // Store the new profile picture
            $filePath = $request->file('profile_picture')->store('profile_pictures', 'public');

            // Update the user's profile picture path
            $user->profile_picture = $filePath;
            $user->save();

            return response()->json([
                'message' => 'Profile picture uploaded successfully',
                'profile_picture' => Storage::url($user->profile_picture),
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
            Storage::disk('public')->delete($user->profile_picture);

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

        $currentUser = Auth::user();

        // Retrieve the student or professor to be suspended
        $student = User::where('id', $studentId)->first();

        if (!$student || ($currentUser->role === 'professor' && $student->role !== 'user')) {
            return response()->json(['message' => 'Unauthorized or Student not found'], 403);
        }

        $student->suspensions()->where('StudentID', $student->id)->delete();

        $student->update([
            'is_suspended' => true,
        ]);

        if ($student->is_suspended) {
            $student->suspensions()->create([
                'Reason' => $request->reason,
                'SuspendedAt' => now(),
            ]);

            return response()->json(['message' => 'User suspended successfully'], 200);
        }

        return response()->json(['message' => 'Student suspended successfully'], 200);
    }

    public function unsuspendStudent($studentId)
    {
        $currentUser = Auth::user();

        // Retrieve the student or professor to be unsuspended
        $student = User::where('id', $studentId)->first();

        if (!$student || ($currentUser->role === 'professor' && $student->role !== 'user')) {
            return response()->json(['message' => 'Unauthorized or Student not found'], 403);
        }

        $student->update([
            'is_suspended' => false,
        ]);

        return response()->json(['message' => 'User unsuspended successfully'], 200);
    }

    public function viewSuspendedStudents()
    {
        $currentUser = Auth::user(); // Get the authenticated user

        // Professors can view only suspended users
        $query = User::where('is_suspended', true)->with('suspensions');

        if ($currentUser->role === 'professor') {
            $query->where('role', 'user');
        }

        $suspendedStudents = $query->get();

        return response()->json(['data' => $suspendedStudents]);
    }
}