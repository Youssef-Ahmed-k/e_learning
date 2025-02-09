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
                ->where('is_read', false)
                ->orderBy('SendAt', 'desc')
                ->select('Message', 'SendAt') 
                ->get();

            return response()->json(['notifications' => $notifications]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Something went wrong', 'message' => $e->getMessage()], 500);
        }
    }
    public function markAsRead($notification_id)
    {
        try {
            // Find the notification and ensure it belongs to the authenticated user
            $notification = Notification::where('RecipientID', auth()->user()->id)
                ->findOrFail($notification_id);

            // Mark the notification as read
            $notification->update(['is_read' => true]);

            return response()->json(['message' => 'Notification marked as read']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Something went wrong', 'message' => $e->getMessage()], 500);
        }
    }
    public function markAllAsRead()
    {
        try {
            // Get the authenticated user's ID
            $user_id = auth()->user()->id;

            // Update all unread notifications
            Notification::where('RecipientID', $user_id)
            ->where('is_read', false)
            ->update(['is_read' => true]);

            return response()->json(['message' => 'All notifications marked as read']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Something went wrong', 'message' => $e->getMessage()], 500);
        }
    }
    public function deleteNotification($notification_id)
    {
        try {
            // Find the notification and ensure it belongs to the authenticated user
            $notification = Notification::where('RecipientID', auth()->user()->id)
                ->findOrFail($notification_id);

            // Delete the notification
            $notification->delete();

            return response()->json(['message' => 'Notification deleted']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Something went wrong', 'message' => $e->getMessage()], 500);
        }
    }
    public function deleteAllNotifications()
    {
        try {
            // Get the authenticated user's ID
            $user_id = auth()->user()->id;

            // Delete all notifications for the user
            Notification::where('RecipientID', $user_id)->delete();

            return response()->json(['message' => 'All notifications deleted']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Something went wrong', 'message' => $e->getMessage()], 500);
        }
    }
}
