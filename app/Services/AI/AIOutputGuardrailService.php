<?php

namespace App\Services\AI;

class AIOutputGuardrailService
{
    /** @return array{safe:bool,reasons:array<int,string>} */
    public function inspectForAutonomousSend(string $text): array
    {
        $reasons = [];
        if (trim($text) === '' || mb_strlen($text) > 50000) $reasons[] = 'invalid_length';
        $patterns = [
            'possible_secret' => '/\b(?:sk-[A-Za-z0-9_-]{16,}|api[_ -]?key\s*[:=]\s*\S+|bearer\s+[A-Za-z0-9._-]{16,})\b/i',
            'payment_card' => '/\b(?:\d[ -]*?){13,19}\b/',
            'completed_financial_action' => '/\b(?:we|i)\s+(?:(?:have|already)\s+)*(?:refunded|charged|credited|paid|reimbursed)\b/i',
            'unverified_side_effect' => '/\b(?:we|i)\s+(?:(?:have|already)\s+)*(?:cancelled|canceled|changed|updated|deleted|shipped|escalated|approved)\b/i',
            'credential_request' => '/\b(?:send|share|provide)\s+(?:your\s+)?(?:password|one[- ]time code|otp|api key|secret)\b/i',
        ];
        foreach ($patterns as $reason => $pattern) if (preg_match($pattern, $text)) $reasons[] = $reason;
        return ['safe' => $reasons === [], 'reasons' => array_values(array_unique($reasons))];
    }
}
