<?php

namespace App\Services\Platform;

use App\Models\NotificationTemplate;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Schema;
use Throwable;

class NotificationTemplateService
{
    public function mail(string $key, array $values, MailMessage $fallback): MailMessage
    {
        try {
            if (! Schema::hasTable('notification_templates')) {
                return $fallback;
            }

            $template = NotificationTemplate::query()
                ->where('key', $key)
                ->where('channel', 'email')
                ->where('status', 'active')
                ->first();

            if (! $template || blank($template->subject) || blank($template->body)) {
                return $fallback;
            }

            $allowedValues = collect($template->variables ?? [])->mapWithKeys(fn (string $variable) => [$variable => $values[$variable] ?? ''])->all();
            return (new MailMessage)
                ->subject(strip_tags($this->render($template->subject, $allowedValues)))
                ->view('mail.platform-template', ['body' => $this->sanitizeHtml($this->render($template->body, $allowedValues))]);
        } catch (Throwable $exception) {
            report($exception);

            return $fallback;
        }
    }

    public function render(string $content, array $values): string
    {
        return strtr($content, collect($values)->mapWithKeys(
            fn (mixed $value, string $key) => ['{{'.$key.'}}' => e((string) $value)]
        )->all());
    }

    private function sanitizeHtml(string $html): string
    {
        $html = preg_replace('#<(script|style|iframe|object|embed|form)[^>]*>.*?</\1>#is', '', $html) ?? '';
        $html = preg_replace('/\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html) ?? '';
        $html = preg_replace('/(href|src)\s*=\s*(["\'])\s*(javascript|data):[^"\']*\2/i', '$1="#"', $html) ?? '';

        return strip_tags($html, '<h1><h2><h3><p><br><strong><b><em><i><u><a><ul><ol><li><blockquote><code><pre><span><div><table><thead><tbody><tr><th><td>');
    }
}
