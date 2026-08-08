<?php

namespace App\Exceptions\Knowledge;

use App\Enums\Knowledge\FailureCategory;
use Throwable;

class ExtractionException extends KnowledgeException
{
    public static function unreadable(string $filename, ?Throwable $previous = null): self
    {
        return new self(
            "Extraction failed for {$filename}: ".($previous?->getMessage() ?? 'unreadable content'),
            FailureCategory::ExtractionError,
            'We could not read any text from this file. It may be a scan, password-protected, or corrupted. '
            .'Enable OCR in Knowledge settings, or upload a text-based copy.',
            $previous,
        );
    }

    public static function unsupported(string $mimeType, string $filename): self
    {
        return new self(
            "No extractor supports {$mimeType} (file: {$filename})",
            FailureCategory::InvalidFile,
            "PromptBot cannot read {$mimeType} files. Convert the file to PDF, DOCX, or plain text and upload it again.",
        );
    }

    public static function empty(string $filename): self
    {
        return new self(
            "Extractor produced no text for {$filename}",
            FailureCategory::ExtractionError,
            'This file contains no readable text, so there is nothing for the AI to learn from it. '
            .'If it is a scanned document, enable OCR in Knowledge settings and retry.',
        );
    }

    public static function archiveGuardTripped(string $filename, string $reason): self
    {
        return new self(
            "Archive guard rejected {$filename}: {$reason}",
            FailureCategory::InvalidFile,
            'This file expands to far more content than its size suggests and was rejected as unsafe. '
            .'Re-save it from its original application and upload again.',
        );
    }
}
