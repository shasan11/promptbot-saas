<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\RendersResourceTable;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Response;

class CommunicationResourceController extends Controller
{
    use RendersResourceTable;

    public function __invoke(Request $request, ?string $resource = 'email-templates'): Response
    {
        $map = [
            'email-templates' => ['Email Templates', 'notification_templates', ['key', 'channel', 'language', 'status', 'subject']],
            'notification-templates' => ['Notification Templates', 'notification_templates', ['key', 'channel', 'language', 'status', 'subject']],
            'announcements' => ['Announcements', 'announcements', ['title', 'status', 'starts_at', 'expires_at']],
        ];

        abort_unless(isset($map[$resource]), 404);
        [$title, $table, $keys] = $map[$resource];

        return $this->tablePage($request, $title, $table, $this->columns($keys));
    }

    private function columns(array $keys): array
    {
        return collect($keys)->map(fn (string $key) => ['key' => $key, 'label' => str($key)->headline()->toString(), 'searchable' => true])->all();
    }
}
