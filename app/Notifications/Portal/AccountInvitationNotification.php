<?php

namespace App\Notifications\Portal;

use App\Models\CustomerAccount;
use App\Services\Platform\NotificationTemplateService;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountInvitationNotification extends Notification
{
    use Queueable;

    public function __construct(public CustomerAccount $account, public string $url) {}

    public function via(object $notifiable): array { return ['mail']; }

    public function toMail(object $notifiable): MailMessage
    {
        $fallback = (new MailMessage)
            ->subject("You're invited to {$this->account->name} on PromptBot")
            ->line("You have been invited to manage {$this->account->name} in the PromptBot Customer Portal.")
            ->action('Accept invitation', $this->url)
            ->line('This invitation expires in seven days.');

        return app(NotificationTemplateService::class)->mail('account_invitation', [
            'platform_name' => config('app.name', 'PromptBot'),
            'customer_name' => data_get($notifiable, 'name', 'there'),
            'account_name' => $this->account->name,
            'action_url' => $this->url,
        ], $fallback);
    }
}
