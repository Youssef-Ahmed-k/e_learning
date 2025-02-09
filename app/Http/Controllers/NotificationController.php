<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
        $this->middleware('role:user');
    }
    public function getUserNotifications()
    {
        try {
            // Get the authenticated user's ID
            $user_id = auth()->user()->id;

            // Fetch all notifications for the user, sorted by latest
            $notifications = Notification::where('RecipientID', $user_id)
                ->orderBy('SendAt', 'desc')
                ->select('Message', 'SendAt') 
                ->get();

            return response()->json(['notifications' => $notifications]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Something went wrong', 'message' => $e->getMessage()], 500);
        }
    }
    public function getUnreadNotifications()
    {
        try {
            // Get the authenticated user's ID
            $user_id = auth()->user()->id;

            // Fetch only unread notifications (ReadAt is NULL)
            $notifications = Notification::where('RecipientID', $user_id)
                ->whereNull('ReadAt')
                ->orderBy('SendAt', 'desc')
                ->select('Message', 'SendAt') 
                ->get();

            return response()->json(['notifications' => $notifications]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Something went wrong', 'message' => $e->getMessage()], 500);
        }
    }
}
