<?php

namespace Tests\Feature\Http\Controllers\Admin;

use App\Models\NotificationTemplate;
use App\Mail\BulkPlatformMail;
use App\Models\PortalUser;
use App\Notifications\Portal\VerifyEmailNotification;
use Database\Seeders\PlatformAuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\InteractsWithPlatformPermissions;
use Tests\TestCase;

class EmailTemplateControllerTest extends TestCase
{
    use InteractsWithPlatformPermissions, RefreshDatabase;

    public function test_template_routes_enforce_view_and_manage_permissions(): void
    {
        $template = NotificationTemplate::create(['key' => 'custom', 'subject' => 'Subject', 'body' => '<p>Body</p>', 'variables' => []]);

        $this->actingAs($this->centralAdminWithPermissions([]), 'central')
            ->get(route('superadmin.communications.email-templates.index'))->assertForbidden();

        $viewer = $this->centralAdminWithPermissions(['communications.view']);
        $this->actingAs($viewer, 'central')
            ->get(route('superadmin.communications.email-templates.index'))->assertOk();
        $this->actingAs($viewer, 'central')
            ->put(route('superadmin.communications.email-templates.update', $template), [
                'subject' => 'Changed', 'body' => '<p>Changed</p>', 'status' => 'active',
            ])->assertForbidden();
    }

    public function test_admin_can_update_and_test_a_template(): void
    {
        Mail::fake();
        $this->seed(PlatformAuthorizationSeeder::class);
        $template = NotificationTemplate::where('key', 'email_verification')->firstOrFail();
        $admin = $this->centralAdminWithPermissions(['communications.view', 'communications.manage']);

        $this->actingAs($admin, 'central')->put(route('superadmin.communications.email-templates.update', $template), [
            'subject' => 'Welcome to {{platform_name}}',
            'body' => '<p>Hello {{customer_name}}</p>',
            'status' => 'active',
        ])->assertRedirect();

        $this->actingAs($admin, 'central')->post(route('superadmin.communications.email-templates.test', $template), [
            'recipient' => 'owner@example.test',
        ])->assertRedirect();

        $this->assertDatabaseHas('notification_templates', ['id' => $template->id, 'subject' => 'Welcome to {{platform_name}}']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'email_template.updated', 'entity_id' => $template->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'email_template.test_sent', 'entity_id' => $template->id]);
    }

    public function test_active_template_is_used_by_portal_notification(): void
    {
        NotificationTemplate::create([
            'key' => 'email_verification', 'channel' => 'email', 'language' => 'en', 'status' => 'active',
            'subject' => 'Verify for {{customer_name}}', 'body' => '<p>Use {{action_url}}</p>',
            'variables' => ['customer_name', 'action_url'],
        ]);
        $user = PortalUser::factory()->create(['name' => 'Alex & Co']);

        $message = (new VerifyEmailNotification)->toMail($user);

        $this->assertSame('Verify for Alex &amp; Co', $message->subject);
        $this->assertSame('mail.platform-template', $message->view);
    }

    public function test_admin_can_queue_a_bulk_email_to_active_portal_users(): void
    {
        Mail::fake();
        PortalUser::factory()->count(2)->create(['status' => 'active']);
        PortalUser::factory()->create(['status' => 'suspended']);

        $this->actingAs($this->centralAdminWithPermissions(['communications.manage']), 'central')
            ->post(route('superadmin.communications.bulk-email.store'), [
                'audience' => 'active',
                'subject' => 'Platform update',
                'body' => '<p>A useful customer update.</p>',
            ])->assertRedirect();

        Mail::assertQueued(BulkPlatformMail::class, 2);
        $this->assertDatabaseHas('audit_logs', ['action' => 'bulk_email.queued']);
    }
}
