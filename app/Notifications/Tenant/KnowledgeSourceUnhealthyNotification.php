<?php

namespace App\Notifications\Tenant;

use App\Models\Knowledge\KnowledgeSource;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells knowledge owners that a source has stopped working.
 *
 * Deliberately NOT sent per failed document. A bad import of 300 files would
 * otherwise generate 300 emails and train everyone to filter them — this fires
 * once when a source transitions to an unhealthy state, and reports the totals.
 */
class KnowledgeSourceUnhealthyNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $sourceName,
        private readonly string $knowledgeBaseName,
        private readonly string $reason,
        private readonly string $remediation,
        private readonly int $affectedItems,
        private readonly string $sourceUrl,
    ) {}

    public static function forSource(KnowledgeSource $source, string $reason, string $remediation, int $affectedItems): self
    {
        return new self(
            $source->name,
            $source->knowledgeBase?->name ?? 'Knowledge base',
            $reason,
            $remediation,
            $affectedItems,
            route('tenant.admin.knowledge.sources.show', $source->uuid),
        );
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject("Knowledge source needs attention: {$this->sourceName}")
            ->greeting('A knowledge source stopped working')
            ->line("**{$this->sourceName}** in *{$this->knowledgeBaseName}* is no longer updating.")
            ->line($this->reason);

        if ($this->affectedItems > 0) {
            $message->line("{$this->affectedItems} item(s) could not be processed.");
        }

        return $message
            ->line($this->remediation)
            ->action('Review the source', $this->sourceUrl)
            ->line('Your AI agents keep answering from the knowledge that was already indexed — only new and changed content is affected.');
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'knowledge.source_unhealthy',
            'source_name' => $this->sourceName,
            'knowledge_base' => $this->knowledgeBaseName,
            'reason' => $this->reason,
            'remediation' => $this->remediation,
            'affected_items' => $this->affectedItems,
            'url' => $this->sourceUrl,
        ];
    }
}
