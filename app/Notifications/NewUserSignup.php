<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewUserSignup extends Notification implements ShouldQueue
{
    use Queueable;

    protected $user;

    /**
     * Create a new notification instance.
     */
    public function __construct(User $user)
    {
        $this->user = $user;
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
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('🎉 New User Signup - ' . $this->user->name)
            ->line('A new user has signed up for Appliflow!')
            ->line('')
            ->line('**User Details:**')
            ->line('Name: ' . $this->user->name)
            ->line('Email: ' . $this->user->email)
            ->line('Signup Method: ' . ($this->user->google_id ? 'Google OAuth' : 'Email/Password'))
            ->line('Subscription: ' . ucfirst($this->user->subscription_plan ?? 'none'))
            ->line('Subscription Status: ' . ucfirst($this->user->subscription_status ?? 'none'))
            ->line('Trial Ends: ' . ($this->user->subscription_ends_at ? $this->user->subscription_ends_at->format('M d, Y') : 'N/A'))
            ->line('')
            ->line('User ID: ' . $this->user->id)
            ->line('Signed up at: ' . $this->user->created_at->format('M d, Y g:i A T'));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'user_id' => $this->user->id,
            'user_name' => $this->user->name,
            'user_email' => $this->user->email,
            'signup_method' => $this->user->google_id ? 'google' : 'email',
        ];
    }
}
