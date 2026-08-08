<?php

namespace App\Enums\Knowledge;

/**
 * Groups raw exceptions into buckets the Failed Sources page can filter on and,
 * critically, decides whether a retry is worth attempting at all — re-running
 * extraction on a corrupt PDF burns queue capacity forever without converging.
 */
enum FailureCategory: string
{
    case UploadError = 'upload_error';
    case ExtractionError = 'extraction_error';
    case CrawlerError = 'crawler_error';
    case EmbeddingProviderError = 'embedding_provider_error';
    case RateLimit = 'rate_limit';
    case StorageError = 'storage_error';
    case InvalidFile = 'invalid_file';
    case AuthenticationError = 'authentication_error';
    case NetworkError = 'network_error';
    case QuotaExceeded = 'quota_exceeded';
    case Unknown = 'unknown';

    public function label(): string
    {
        return match ($this) {
            self::UploadError => 'Upload error',
            self::ExtractionError => 'Extraction error',
            self::CrawlerError => 'Crawler error',
            self::EmbeddingProviderError => 'Embedding provider error',
            self::RateLimit => 'Rate limited',
            self::StorageError => 'Storage error',
            self::InvalidFile => 'Invalid file',
            self::AuthenticationError => 'Authentication error',
            self::NetworkError => 'Network error',
            self::QuotaExceeded => 'Quota exceeded',
            self::Unknown => 'Unknown error',
        };
    }

    /** Transient categories are safe to retry with backoff. */
    public function isTransient(): bool
    {
        return in_array($this, [
            self::EmbeddingProviderError, self::RateLimit, self::NetworkError, self::StorageError,
        ], true);
    }

    /**
     * Categories where retrying can never succeed without human action; the
     * source is parked in `attention_required` instead of being re-queued.
     */
    public function requiresAttention(): bool
    {
        return in_array($this, [self::AuthenticationError, self::QuotaExceeded], true);
    }

    /** Operator-facing guidance shown verbatim on the Failed Sources page. */
    public function remediation(): string
    {
        return match ($this) {
            self::UploadError => 'The file did not finish uploading. Try uploading it again.',
            self::ExtractionError => 'We could not read text from this file. It may be scanned or corrupted — enable OCR in Knowledge settings, or upload a text-based copy.',
            self::CrawlerError => 'The page could not be fetched. Check that the URL is public and that the site is reachable.',
            self::EmbeddingProviderError => 'The embedding provider rejected the request. Check the provider credentials in Knowledge settings, then retry.',
            self::RateLimit => 'The provider rate-limited us. This retries automatically with backoff — no action needed unless it persists.',
            self::StorageError => 'The file could not be written to storage. Check the workspace storage quota.',
            self::InvalidFile => 'This file type or structure is not supported. Convert it to PDF, DOCX, or plain text and upload again.',
            self::AuthenticationError => 'The connected account is no longer authorised. Reconnect the source to restore syncing.',
            self::NetworkError => 'A network error interrupted processing. Retry the source.',
            self::QuotaExceeded => 'This workspace has reached its knowledge usage limit. Upgrade the plan or remove unused sources.',
            self::Unknown => 'Processing failed for an unexpected reason. Retry the source, and contact support if it keeps failing.',
        };
    }
}
