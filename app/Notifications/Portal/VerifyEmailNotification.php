<?php

namespace App\Notifications\Portal;

use App\Services\Platform\NotificationTemplateService;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class VerifyEmailNotification extends Notification
{
    use Queueable;
    public function via(object $notifiable): array { return ['mail']; }
    public function toMail(object $notifiable): MailMessage
    {
        $url = URL::temporarySignedRoute('portal.verification.verify', now()->addMinutes(60), ['id' => $notifiable->getKey(), 'hash' => sha1($notifiable->getEmailForVerification())]);
        $fallback = (new MailMessage)->subject('Verify your PromptBot customer account')->line('Verify your email address to secure your Customer Portal account.')->action('Verify email', $url);
        return app(NotificationTemplateService::class)->mail('email_verification', [
            'platform_name' => config('app.name', 'PromptBot'), 'customer_name' => data_get($notifiable, 'name', 'there'), 'action_url' => $url,
        ], $fallback);
    }
}
