<?php

namespace App\Services\AI;

use App\Enums\AI\ApprovalStatus;
use App\Models\AI\ApprovalRequest;
use App\Models\AI\ToolCall;
use App\Models\User;
use App\Services\Tenant\TenantAuditLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class ApprovalService
{
    public function __construct(private readonly AIToolExecutionService $tools, private readonly TenantAuditLogService $audit) {}

    public function approve(ApprovalRequest $approval, User $actor, ?string $reason = null): void
    {
        DB::transaction(function () use ($approval, $actor, $reason) {
            $locked = ApprovalRequest::query()->lockForUpdate()->findOrFail($approval->id); $this->ensurePending($locked);
            $locked->forceFill(['status' => ApprovalStatus::Approved, 'decided_by' => $actor->id, 'decided_at' => now(), 'decision_reason' => $reason])->save();
        });
        try {
            $this->tools->executeApproved($approval->fresh(), $actor);
            $approval->forceFill(['status' => ApprovalStatus::Executed])->save();
            $this->audit->record('ai.approval_executed', $actor, 'Approved and executed AI tool action', $approval, newValues: ['status' => 'executed'], subjectLabel: $approval->requested_action);
        } catch (Throwable $exception) {
            $approval->forceFill(['status' => ApprovalStatus::Failed])->save();
            $this->audit->record('ai.approval_failed', $actor, 'Approved AI tool action failed safely', $approval, newValues: ['status' => 'failed'], subjectLabel: $approval->requested_action);
            throw ValidationException::withMessages(['approval' => 'The approval was recorded, but the action failed safely.']);
        }
    }

    public function reject(ApprovalRequest $approval, User $actor, ?string $reason = null): void
    {
        DB::transaction(function () use ($approval, $actor, $reason) {
            $locked = ApprovalRequest::query()->lockForUpdate()->findOrFail($approval->id); $this->ensurePending($locked);
            $locked->forceFill(['status' => ApprovalStatus::Rejected, 'decided_by' => $actor->id, 'decided_at' => now(), 'decision_reason' => $reason])->save();
            ToolCall::query()->whereKey($approval->tool_call_id)->update(['status' => 'rejected', 'finished_at' => now(), 'error_code' => 'APPROVAL_REJECTED', 'error_message_safe' => 'A reviewer rejected this action.']);
        });
        $this->audit->record('ai.approval_rejected', $actor, 'Rejected AI tool action', $approval, newValues: ['status' => 'rejected'], subjectLabel: $approval->requested_action);
    }

    private function ensurePending(ApprovalRequest $approval): void
    {
        if ($approval->status !== ApprovalStatus::Pending) throw ValidationException::withMessages(['approval' => 'This approval request has already been decided.']);
        if ($approval->expires_at?->isPast()) {
            $approval->forceFill(['status' => ApprovalStatus::Expired])->save();
            throw ValidationException::withMessages(['approval' => 'This approval request has expired.']);
        }
    }
}
