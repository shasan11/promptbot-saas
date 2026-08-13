<?php

namespace App\Notifications\Portal;

use App\Services\Platform\NotificationTemplateService;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification
{
    use Queueable;
    public function __construct(public string $token) {}
    public function via(object $notifiable): array { return ['mail']; }
    public function toMail(object $notifiable): MailMessage
    {
        $url = route('portal.password.reset', ['token' => $this->token, 'email' => $notifiable->getEmailForPasswordReset()]);
        $fallback = (new MailMessage)->subject('Reset your PromptBot Customer Portal password')->line('A password reset was requested for your customer account.')->action('Reset password', $url)->line('If you did not request this, no action is needed.');
        return app(NotificationTemplateService::class)->mail('password_reset', [
            'platform_name' => config('app.name', 'PromptBot'), 'customer_name' => data_get($notifiable, 'name', 'there'), 'action_url' => $url,
        ], $fallback);
    }
}
