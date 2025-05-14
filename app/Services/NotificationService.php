<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;

class NotificationService
{
    public static function sendToCourseStudents($course_id, $message, $type)
    {
        $students = User::where('role', 'user')
            ->whereHas('courseRegistrations', function ($query) use ($course_id) {
                $query->where('CourseID', $course_id);
            })->get();

        foreach ($students as $student) {
            Notification::create([
                'Message' => $message,
                'SendAt' => now(),
                'RecipientID' => $student->id,
                'type' => $type,
                'CourseID' => $course_id,
            ]);
        }
    }

    public static function sendNotification($recipient_id, $message)
    {
        Notification::create([
            'Message' => $message,
            'SendAt' => now(),
            'RecipientID' => $recipient_id,
        ]);
    }
}