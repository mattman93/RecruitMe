<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class AutoApplyDigest extends Mailable
{
    use Queueable, SerializesModels;

    public User $user;
    public Collection $applications;
    public string $frequency;

    /**
     * Create a new message instance.
     */
    public function __construct(User $user, Collection $applications, string $frequency)
    {
        $this->user = $user;
        $this->applications = $applications;
        $this->frequency = $frequency;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $subject = match($this->frequency) {
            'daily' => 'Your Daily Auto-Apply Report - ' . $this->applications->count() . ' Applications',
            'weekly' => 'Your Weekly Auto-Apply Report - ' . $this->applications->count() . ' Applications',
            default => 'Auto-Apply Report',
        };

        return new Envelope(
            subject: $subject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.auto-apply-digest',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
