<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NotificationController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
        // $this->middleware('role:user | professor');
    }
    public function getUserNotifications()
    {
        try {
            $user_id = auth()->user()->id;

            // Fetch notifications with pagination
            $notifications = Notification::where('RecipientID', $user_id)
                ->orderBy('SendAt', 'desc')
                ->select('NotificationID', 'Message', 'SendAt', 'Type', 'CourseID', 'is_read')
                ->paginate(10); // Paginate with 10 per page

            return response()->json([
                'notifications' => $notifications->items(),
                'pagination' => [
                    'current_page' => $notifications->currentPage(),
                    'total_pages' => $notifications->lastPage(),
                    'total_items' => $notifications->total(),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Something went wrong', 'message' => $e->getMessage()], 500);
        }
    }
    public function getUnreadNotifications()
    {
        try {
            $user_id = auth()->user()->id;

            // Fetch unread notifications with pagination
            $notifications = Notification::where('RecipientID', $user_id)
                ->where('is_read', false)
                ->orderBy('SendAt', 'desc')
                ->select('NotificationID', 'Message', 'SendAt', 'Type', 'CourseID', 'is_read')
                ->paginate(10); // Paginate with 10 per page

            return response()->json([
                'notifications' => $notifications->items(),
                'pagination' => [
                    'current_page' => $notifications->currentPage(),
                    'total_pages' => $notifications->lastPage(),
                    'total_items' => $notifications->total(),
                ]
            ]);
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
            $notification->update(['is_read' => 1, 'SendAt' => DB::raw('SendAt')]);

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
                ->update(['is_read' => 1, 'SendAt' => DB::raw('SendAt')]);

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

    public function getUnreadNotificationCount()
    {
        try {
            $user_id = auth()->user()->id;
            $unreadCount = Notification::where('RecipientID', $user_id)
                ->where('is_read', false)
                ->count();
            return response()->json(['unread_count' => $unreadCount]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Something went wrong', 'message' => $e->getMessage()], 500);
        }
    }
}
