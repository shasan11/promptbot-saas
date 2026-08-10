<?php

namespace App\Enums\AI;

enum ErrorCategory: string
{
    case Authentication = 'authentication';
    case Authorization = 'authorization';
    case RateLimit = 'rate_limit';
    case Quota = 'quota';
    case Timeout = 'timeout';
    case Network = 'network';
    case ProviderUnavailable = 'provider_unavailable';
    case InvalidModel = 'invalid_model';
    case InvalidRequest = 'invalid_request';
    case StructuredOutputInvalid = 'structured_output_invalid';
    case ToolError = 'tool_error';
    case ToolDenied = 'tool_denied';
    case ApprovalRejected = 'approval_rejected';
    case KnowledgeUnavailable = 'knowledge_unavailable';
    case GuardrailBlocked = 'guardrail_blocked';
    case BudgetExceeded = 'budget_exceeded';
    case Unknown = 'unknown';
}
