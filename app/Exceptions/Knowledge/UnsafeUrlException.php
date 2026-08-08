<?php

namespace App\Exceptions\Knowledge;

use App\Enums\Knowledge\FailureCategory;

/**
 * Raised by UrlSafetyGuard when a URL would make the crawler reach somewhere it
 * must not — loopback, private ranges, cloud metadata endpoints, non-HTTP
 * schemes. The operator message deliberately does NOT echo the resolved IP: a
 * crawl target is user-supplied, and reporting "this resolved to 169.254.169.254"
 * turns the crawler into an internal network scanner with a readable UI.
 */
class UnsafeUrlException extends KnowledgeException
{
    public static function blocked(string $url, string $reason): self
    {
        return new self(
            "Refused to fetch [{$url}]: {$reason}",
            FailureCategory::CrawlerError,
            'This URL points somewhere PromptBot is not allowed to fetch from. Enter a public website address.',
        );
    }
}
