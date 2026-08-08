<?php

namespace App\Notifications\Tenant;

use App\Models\TenantInvitation;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TenantInvitationNotification extends Notification
{
    public function __construct(
        private readonly TenantInvitation $invitation,
        private readonly string $acceptUrl,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('You have been invited to join a PromptBot workspace')
            ->greeting('Hello'.($this->invitation->name ? " {$this->invitation->name}" : '').',')
            ->line($this->invitation->message ?: "You've been invited to join a PromptBot workspace.")
            ->action('Accept invitation', $this->acceptUrl)
            ->line('This invitation expires on '.$this->invitation->expires_at->format('M j, Y').'.');
    }
}
