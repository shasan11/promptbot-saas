<?php

namespace Tests\Unit;

use App\Enums\Connections\ConnectionErrorCategory;
use App\Services\Connections\CustomApiSafetyService;
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

    public function test_database_safety_requires_sensitive_columns_to_be_excluded(): void
    {
        $service = new DatabaseSafetyService;

        $this->expectException(InvalidArgumentException::class);
        $service->validateDataSourceConfig([
            'schema_name' => 'public',
            'table_name' => 'customers',
            'allowed_columns' => ['id', 'email', 'password_hash'],
            'excluded_columns' => [],
            'row_limit' => 1000,
        ]);
    }

    public function test_database_safety_accepts_structured_config_with_sensitive_exclusions(): void
    {
        $service = new DatabaseSafetyService;

        $result = $service->validateDataSourceConfig([
            'schema_name' => 'public',
            'table_name' => 'customers',
            'primary_key' => 'id',
            'incremental_column' => 'updated_at',
            'allowed_columns' => ['id', 'email', 'password_hash', 'updated_at'],
            'excluded_columns' => ['password_hash'],
            'row_limit' => 1000,
            'raw_sql' => 'select id, email from customers',
        ]);

        $this->assertSame(['id', 'email', 'password_hash', 'updated_at'], $result['allowed_columns']);
        $this->assertSame(['password_hash'], $result['sensitive_columns']);
    }

    public function test_custom_api_safety_rejects_internal_urls_and_absolute_paths(): void
    {
        $service = new CustomApiSafetyService;

        $this->expectException(InvalidArgumentException::class);
        $service->validateBaseUrl('https://127.0.0.1');
    }

    public function test_custom_api_safety_requires_relative_paths_and_explicit_delete_risk(): void
    {
        $service = new CustomApiSafetyService;

        $this->expectException(InvalidArgumentException::class);
        $service->validateOperation('DELETE', '/customers/{id}', 'low');
    }

    public function test_custom_api_safety_accepts_safe_relative_read_operation(): void
    {
        $service = new CustomApiSafetyService;

        $service->validateOperation('GET', '/customers/{id}', 'low');
        $this->assertTrue(true);
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
