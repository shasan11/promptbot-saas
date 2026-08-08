<?php

namespace App\Services\Knowledge;

use App\Enums\Knowledge\DocumentStatus;
use App\Enums\Knowledge\FailureCategory;
use App\Enums\Knowledge\ProcessingStage;
use App\Exceptions\Knowledge\KnowledgeException;
use App\Models\Knowledge\KnowledgeDocument;
use App\Models\Knowledge\KnowledgeSource;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Validates and stores uploaded files, and decides what to do about duplicates.
 *
 * Validation here is deliberately not the same as the form request's. The form
 * request checks what the browser claimed; this checks what the bytes actually
 * are. A file named `policy.pdf` whose magic bytes say `application/x-php` is
 * rejected here, not accepted because the extension looked fine.
 */
class DocumentUploadService
{
    public const ON_DUPLICATE_SKIP = 'skip';

    public const ON_DUPLICATE_REPLACE = 'replace';

    public const ON_DUPLICATE_ADD = 'add';

    public function __construct(
        private readonly KnowledgeStorage $storage,
        private readonly KnowledgeLimitService $limits,
        private readonly KnowledgeStatisticsService $statistics,
    ) {}

    /**
     * @return array{document: ?KnowledgeDocument, status: string, duplicate_of: ?KnowledgeDocument}
     *
     * @throws KnowledgeException
     */
    public function store(
        KnowledgeSource $source,
        UploadedFile $file,
        ?User $actor,
        string $onDuplicate = self::ON_DUPLICATE_SKIP,
        ?int $collectionId = null,
    ): array {
        $this->limits->assertWithinLimit('documents');
        $this->limits->assertWithinLimit('storage_bytes', $file->getSize() ?: 0);

        $originalName = $this->sanitiseFilename($file->getClientOriginalName());
        $extension = Str::lower(pathinfo($originalName, PATHINFO_EXTENSION));
        $mimeType = $this->detectMimeType($file);

        $this->assertAcceptable($file, $mimeType, $extension, $originalName);

        $checksum = hash_file('sha256', $file->getRealPath());

        // Duplicate detection is by content, not filename. Re-uploading the same
        // bytes should not cost a second round of embedding.
        $duplicate = KnowledgeDocument::query()
            ->where('knowledge_base_id', $source->knowledge_base_id)
            ->where('checksum', $checksum)
            ->first();

        if ($duplicate && $onDuplicate === self::ON_DUPLICATE_SKIP) {
            return ['document' => null, 'status' => 'skipped_duplicate', 'duplicate_of' => $duplicate];
        }

        if ($duplicate && $onDuplicate === self::ON_DUPLICATE_REPLACE) {
            return [
                'document' => $this->replaceFile($duplicate, $file, $originalName, $mimeType, $extension, $checksum, $actor),
                'status' => 'replaced',
                'duplicate_of' => $duplicate,
            ];
        }

        $document = new KnowledgeDocument([
            'knowledge_base_id' => $source->knowledge_base_id,
            'knowledge_source_id' => $source->id,
            'knowledge_collection_id' => $collectionId ?? $source->knowledge_collection_id,
            'title' => $this->titleFromFilename($originalName),
            'kind' => KnowledgeDocument::KIND_FILE,
            'original_filename' => $originalName,
            'mime_type' => $mimeType,
            'extension' => $extension,
            'file_size' => (int) $file->getSize(),
            'checksum' => $checksum,
            'created_by' => $actor?->id,
            'updated_by' => $actor?->id,
        ]);

        $document->uuid = (string) Str::uuid();
        $document->status = DocumentStatus::Uploaded;
        $document->current_stage = ProcessingStage::Uploaded;
        $document->storage_disk = $this->storage->diskName();
        $document->storage_path = $this->storage->store($file, $source->knowledgeBase->uuid, $document->uuid);
        $document->save();

        $this->statistics->refreshForSource($source);

        return ['document' => $document, 'status' => 'stored', 'duplicate_of' => $duplicate];
    }

    /**
     * Replaces a document's file, keeping the old version.
     *
     * The existing chunks stay live and retrievable until the replacement
     * finishes processing — a busy support team should not lose the ability to
     * answer questions for the minutes it takes to re-index a large PDF.
     * KnowledgeVersionService flips the active pointer once the new version is
     * ready.
     */
    public function replaceFile(
        KnowledgeDocument $document,
        UploadedFile $file,
        ?string $originalName = null,
        ?string $mimeType = null,
        ?string $extension = null,
        ?string $checksum = null,
        ?User $actor = null,
    ): KnowledgeDocument {
        $originalName ??= $this->sanitiseFilename($file->getClientOriginalName());
        $mimeType ??= $this->detectMimeType($file);
        $extension ??= Str::lower(pathinfo($originalName, PATHINFO_EXTENSION));
        $checksum ??= hash_file('sha256', $file->getRealPath());

        $this->assertAcceptable($file, $mimeType, $extension, $originalName);

        $document->forceFill([
            'original_filename' => $originalName,
            'mime_type' => $mimeType,
            'extension' => $extension,
            'file_size' => (int) $file->getSize(),
            'checksum' => $checksum,
            'storage_disk' => $this->storage->diskName(),
            'storage_path' => $this->storage->store($file, $document->knowledgeBase->uuid, $document->uuid),
            'updated_by' => $actor?->id,
        ])->save();

        return $document;
    }

    /**
     * Rejects anything the platform will not ingest.
     *
     * @throws KnowledgeException
     */
    private function assertAcceptable(UploadedFile $file, string $mimeType, string $extension, string $filename): void
    {
        if (! $file->isValid()) {
            throw new KnowledgeException(
                "Upload of {$filename} did not complete",
                FailureCategory::UploadError,
                'The file did not finish uploading. Check your connection and try again.',
            );
        }

        $maxKb = (int) config('knowledge.uploads.max_file_size_kb');

        if (($file->getSize() ?: 0) > $maxKb * 1024) {
            throw new KnowledgeException(
                "{$filename} exceeds the {$maxKb}KB upload limit",
                FailureCategory::UploadError,
                'This file is larger than the workspace upload limit of '.round($maxKb / 1024).' MB. '
                .'Split it into smaller documents, or ask an administrator to raise the limit.',
            );
        }

        $map = (array) config('knowledge.uploads.mime_map');

        if (! isset($map[$mimeType])) {
            throw new KnowledgeException(
                "Rejected {$filename}: detected type {$mimeType} is not allowed",
                FailureCategory::InvalidFile,
                "PromptBot cannot read {$mimeType} files. Supported formats are PDF, Word, Excel, PowerPoint, "
                .'text, Markdown, CSV, HTML and JSON.',
            );
        }

        // The decisive check: the extension must be one this content type is
        // legitimately served under. A PHP script renamed to .pdf sniffs as
        // text/x-php and never reaches here; a .exe renamed to .pdf fails here.
        if (! in_array($extension, $map[$mimeType], true)) {
            throw new KnowledgeException(
                "Rejected {$filename}: extension .{$extension} does not match detected type {$mimeType}",
                FailureCategory::InvalidFile,
                "The contents of this file do not match its .{$extension} extension, so it was rejected as unsafe. "
                .'Re-save the file in its proper format and upload again.',
            );
        }
    }

    /**
     * Sniffs the real content type. `getMimeType()` reads magic bytes via
     * finfo; `getClientMimeType()` is whatever the browser asserted and is not
     * trusted for anything.
     */
    private function detectMimeType(UploadedFile $file): string
    {
        $detected = $file->getMimeType() ?: 'application/octet-stream';

        // finfo reports Office Open XML containers as generic zip archives on
        // some builds. Where the extension agrees with a known OOXML type, the
        // more specific type is used — verified structurally at extraction time,
        // where a non-OOXML zip fails on its missing parts.
        if (in_array($detected, ['application/zip', 'application/octet-stream'], true)) {
            $byExtension = match (Str::lower($file->getClientOriginalExtension())) {
                'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                default => null,
            };

            if ($byExtension && $this->looksLikeOfficeArchive($file->getRealPath())) {
                return $byExtension;
            }
        }

        return $detected;
    }

    /** Confirms a zip really is an OOXML package before trusting the extension. */
    private function looksLikeOfficeArchive(string $path): bool
    {
        $zip = new \ZipArchive;

        if ($zip->open($path) !== true) {
            return false;
        }

        $hasContentTypes = $zip->locateName('[Content_Types].xml') !== false;
        $zip->close();

        return $hasContentTypes;
    }

    /**
     * Strips directory components and control characters. The result is only
     * ever stored as a display column — it never becomes part of a filesystem
     * path — but a filename echoed into the UI is still untrusted text.
     */
    private function sanitiseFilename(string $filename): string
    {
        $filename = basename(str_replace('\\', '/', $filename));
        $filename = preg_replace('/[\x00-\x1F\x7F]/u', '', $filename) ?? $filename;

        return Str::limit(trim($filename), 240, '');
    }

    private function titleFromFilename(string $filename): string
    {
        $name = pathinfo($filename, PATHINFO_FILENAME);
        $name = str_replace(['_', '-'], ' ', $name);

        return Str::limit(Str::title(trim(preg_replace('/\s+/', ' ', $name) ?? $name)), 200, '');
    }
}
