<?php

namespace App\Notifications\Portal;

use App\Services\Platform\NotificationTemplateService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PlatformEventNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $templateKey,
        private readonly string $title,
        private readonly ?string $body,
        private readonly ?string $url,
        private readonly array $values = [],
    ) {}

    public function via(object $notifiable): array { return ['mail']; }

    public function toMail(object $notifiable): MailMessage
    {
        $fallback = (new MailMessage)->subject($this->title)->greeting($this->title);
        if ($this->body) $fallback->line($this->body);
        if ($this->url) $fallback->action('View in customer portal', url($this->url));

        return app(NotificationTemplateService::class)->mail($this->templateKey, [
            'platform_name' => config('app.name', 'PromptBot'),
            'customer_name' => $notifiable->name,
            'account_name' => $this->values['account_name'] ?? '',
            'workspace_name' => $this->values['workspace_name'] ?? '',
            'invoice_number' => $this->values['invoice_number'] ?? '',
            'invoice_total' => $this->values['invoice_total'] ?? '',
            'payment_amount' => $this->values['payment_amount'] ?? '',
            'ticket_number' => $this->values['ticket_number'] ?? '',
            'action_url' => $this->url ? url($this->url) : url('/account'),
        ], $fallback);
    }
}
