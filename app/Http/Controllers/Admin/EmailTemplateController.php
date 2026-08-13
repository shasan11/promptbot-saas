<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NotificationTemplate;
use App\Services\Platform\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class EmailTemplateController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/EmailTemplates/Index', [
            'templates' => NotificationTemplate::query()
                ->where('channel', 'email')
                ->orderBy('key')
                ->get(),
            'sampleValues' => $this->sampleValues(),
        ]);
    }

    public function update(Request $request, NotificationTemplate $template, AuditLogService $audit): RedirectResponse
    {
        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:100000'],
            'status' => ['required', 'in:draft,active'],
        ]);

        $old = $template->only(['subject', 'body', 'status']);
        $template->update($validated);

        $audit->record('email_template.updated', $template, [
            'old_values' => $old,
            'new_values' => $validated,
        ]);

        return back()->with('status', 'Email template updated.');
    }

    public function test(Request $request, NotificationTemplate $template, AuditLogService $audit): RedirectResponse
    {
        $validated = $request->validate(['recipient' => ['required', 'email', 'max:255']]);
        $subject = $this->render($template->subject ?? '', $this->sampleValues());
        $body = $this->render($template->body ?? '', $this->sampleValues());

        try {
            Mail::html($body, fn ($message) => $message->to($validated['recipient'])->subject($subject));
            $audit->record('email_template.test_sent', $template, [
                'new_values' => ['recipient' => $validated['recipient']],
            ]);

            return back()->with('status', 'Test email sent to '.$validated['recipient'].'.');
        } catch (Throwable $exception) {
            report($exception);
            $audit->record('email_template.test_failed', $template, [
                'new_values' => ['recipient' => $validated['recipient']],
                'severity' => 'warning',
            ]);

            return back()->with('error', 'Test delivery failed. Check the mail configuration and application logs.');
        }
    }

    private function render(string $content, array $values): string
    {
        return strtr($content, collect($values)->mapWithKeys(
            fn (string $value, string $key) => ['{{'.$key.'}}' => $value]
        )->all());
    }

    private function sampleValues(): array
    {
        return [
            'platform_name' => config('app.name', 'PromptBot'),
            'customer_name' => 'Alex Morgan',
            'account_name' => 'Acme Corporation',
            'workspace_name' => 'Acme Support',
            'action_url' => config('app.url').'/portal',
            'invoice_number' => 'INV-10042',
            'invoice_total' => '$149.00',
            'payment_amount' => '$149.00',
            'ticket_number' => 'SUP-2048',
            'temporary_password' => 'example-only',
        ];
    }
}
