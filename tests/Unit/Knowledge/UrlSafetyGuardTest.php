<?php

namespace Tests\Unit\Knowledge;

use App\Exceptions\Knowledge\UnsafeUrlException;
use App\Services\Knowledge\Crawler\UrlSafetyGuard;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * SSRF regression tests.
 *
 * Every case here is an attack the crawler would otherwise carry out on behalf
 * of whoever supplied the URL.
 */
class UrlSafetyGuardTest extends TestCase
{
    private UrlSafetyGuard $guard;

    protected function setUp(): void
    {
        parent::setUp();
        config(['knowledge.crawler.security.allow_private_networks' => false]);
        $this->guard = new UrlSafetyGuard;
    }

    public static function blockedUrls(): array
    {
        return [
            'loopback IPv4' => ['http://127.0.0.1/admin'],
            'loopback name' => ['http://localhost/admin'],
            'loopback IPv6' => ['http://[::1]/'],
            'private 10/8' => ['http://10.0.0.5/'],
            'private 192.168/16' => ['http://192.168.1.1/'],
            'private 172.16/12' => ['http://172.16.0.1/'],
            'AWS metadata' => ['http://169.254.169.254/latest/meta-data/'],
            'GCP metadata' => ['http://metadata.google.internal/'],
            'file scheme' => ['file:///etc/passwd'],
            'gopher scheme' => ['gopher://127.0.0.1:11211/'],
            'dict scheme' => ['dict://127.0.0.1:11211/'],
            'embedded credentials' => ['http://user:pass@example.com/'],
            'bare hostname' => ['http://intranet/'],
            'link-local IPv6' => ['http://[fe80::1]/'],
            'unique local IPv6' => ['http://[fc00::1]/'],
            'IPv4-mapped IPv6 loopback' => ['http://[::ffff:127.0.0.1]/'],
        ];
    }

    #[DataProvider('blockedUrls')]
    public function test_it_refuses_unsafe_targets(string $url): void
    {
        $this->assertFalse($this->guard->isSafe($url), "Guard allowed {$url}");
    }

    public function test_it_throws_with_an_operator_safe_message(): void
    {
        try {
            $this->guard->assertSafe('http://169.254.169.254/latest/meta-data/');
            $this->fail('Expected UnsafeUrlException.');
        } catch (UnsafeUrlException $e) {
            // The message must not confirm what the host resolved to, or the
            // crawl form becomes an internal network scanner.
            $this->assertStringNotContainsString('169.254', $e->operatorMessage());
            $this->assertStringContainsString('public website address', $e->operatorMessage());
        }
    }

    public function test_it_allows_public_addresses(): void
    {
        $this->assertTrue($this->guard->isSafe('https://8.8.8.8/'));
        $this->assertTrue($this->guard->isSafe('http://93.184.216.34/'));
    }

    public function test_cidr_matching_is_family_aware(): void
    {
        // An IPv4 address must never be judged against an IPv6 range.
        $this->assertTrue($this->guard->inCidr('10.1.2.3', '10.0.0.0/8'));
        $this->assertFalse($this->guard->inCidr('11.1.2.3', '10.0.0.0/8'));
        $this->assertFalse($this->guard->inCidr('10.1.2.3', 'fc00::/7'));
        $this->assertTrue($this->guard->inCidr('fc00::1', 'fc00::/7'));
    }

    public function test_cidr_matching_handles_non_byte_aligned_prefixes(): void
    {
        $this->assertTrue($this->guard->inCidr('100.64.0.1', '100.64.0.0/10'));
        $this->assertTrue($this->guard->inCidr('100.127.255.254', '100.64.0.0/10'));
        $this->assertFalse($this->guard->inCidr('100.128.0.1', '100.64.0.0/10'));
    }

    public function test_private_networks_can_be_allowed_explicitly(): void
    {
        // Some installs genuinely need to crawl an intranet. That has to be a
        // deliberate opt-in, never the default.
        config(['knowledge.crawler.security.allow_private_networks' => true]);

        $this->assertTrue((new UrlSafetyGuard)->isSafe('http://10.0.0.5/docs'));
    }
}
