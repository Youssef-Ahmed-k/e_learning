<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Ichtrojan\Otp\Otp;

class ResetPasswordVerificationNotification extends Notification
{
    use Queueable;

    public $message;
    public $subject;
    public $fromEmail;
    public $mailer;
    private $otp;

    /**
     * Create a new notification instance.
     */
    public function __construct()
    {
        $this->message = 'Use the code below to reset your password.';
        $this->subject = 'Reset Password';
        $this->fromEmail = "from@example.com";
        $this->mailer = "smtp";
        $this->otp = new Otp();
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable)
    {
        // Ensure the email is set
        if (empty($notifiable->email)) {
            throw new \Exception('The notifiable object must have a valid email.');
        }

        // Generate OTP
        $otp = $this->otp->generate($notifiable->email, 'numeric', 4, 10);

        // Ensure OTP generation was successful
        if (empty($otp->token)) {
            throw new \Exception('Failed to generate OTP.');
        }

        return (new MailMessage)
            ->mailer('smtp')
            ->subject($this->subject)
            ->greeting('Hello ' . ($notifiable->first_name ?? 'User') . ',')
            ->line($this->message)
            ->line('Your OTP is: ' . $otp->token)
            ->line('This OTP will expire in 10 minutes.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [];
    }
}
