<?php

namespace App\Notifications;

use App\Models\AI\ApprovalRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AIApprovalRequested extends Notification
{
    use Queueable;
    public function __construct(private readonly ApprovalRequest $approval) {}
    public function via(object $notifiable): array { return ['database']; }
    public function toArray(object $notifiable): array
    {
        return ['event' => 'ai.approval_requested', 'title' => 'AI action needs approval', 'message' => $this->approval->requested_action.' requires review.', 'url' => route('tenant.admin.ai.approvals.index'), 'risk_level' => $this->approval->risk_level->value, 'approval_uuid' => $this->approval->public_uuid];
    }
}
