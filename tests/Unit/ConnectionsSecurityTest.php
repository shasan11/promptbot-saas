<?php

namespace Tests\Unit;

use App\Enums\Connections\ConnectionErrorCategory;
use App\Services\Connections\DatabaseSafetyService;
use App\Services\Connections\RetryPolicyService;
use App\Services\Connections\SecretRedactor;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ConnectionsSecurityTest extends TestCase
{
    public function test_secret_redactor_masks_nested_sensitive_values(): void
    {
        $redacted = (new SecretRedactor)->redact([
            'Authorization' => 'Bearer super-secret-token',
            'nested' => ['api_key' => 'sk_live_123'],
        ]);

        $this->assertSame('[redacted]', $redacted['Authorization']);
        $this->assertSame('[redacted]', $redacted['nested']['api_key']);
    }

    public function test_database_safety_rejects_private_destinations_and_unsafe_sql(): void
    {
        $service = new DatabaseSafetyService;

        $this->expectException(InvalidArgumentException::class);
        $service->validateDestination('127.0.0.1');
    }

    public function test_database_safety_rejects_non_select_queries(): void
    {
        $service = new DatabaseSafetyService;

        $this->expectException(InvalidArgumentException::class);
        $service->validateSelectOnlyQuery('delete from users');
    }

    public function test_retry_policy_is_bounded_and_failure_type_aware(): void
    {
        $policy = new RetryPolicyService;

        $this->assertSame(ConnectionErrorCategory::RateLimit, $policy->classify(429));
        $this->assertTrue($policy->shouldRetry(ConnectionErrorCategory::RateLimit, 1));
        $this->assertFalse($policy->shouldRetry(ConnectionErrorCategory::Authentication, 1));
        $this->assertFalse($policy->shouldRetry(ConnectionErrorCategory::Timeout, 5));
    }
}
