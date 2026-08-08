<?php

namespace App\Services\Knowledge;

use App\Models\Knowledge\KnowledgeDocument;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Private object storage for original uploads.
 *
 * Files are laid out per tenant and per knowledge base:
 *
 *   tenants/{tenant}/knowledge/{base_uuid}/{document_uuid}.{ext}
 *
 * The stored filename is the document UUID, never the user-supplied one. That
 * closes path traversal at the source (no user input reaches the path at all)
 * and keeps a filename like "Q4 pay — final(2).pdf" from producing an unusable
 * key. The original name is kept as a column for display and download.
 *
 * Nothing here is ever public. Downloads go through a signed, short-lived,
 * policy-checked route rather than a permanent URL.
 */
class KnowledgeStorage
{
    public function disk(): Filesystem
    {
        return Storage::disk($this->diskName());
    }

    public function diskName(): string
    {
        return (string) config('knowledge.storage.disk');
    }

    /** Stores an upload and returns its storage path. */
    public function store(UploadedFile $file, string $baseUuid, string $documentUuid): string
    {
        $extension = Str::lower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'bin');
        $path = $this->directory($baseUuid).'/'.$documentUuid.'.'.preg_replace('/[^a-z0-9]/', '', $extension);

        $this->disk()->put($path, file_get_contents($file->getRealPath()), ['visibility' => 'private']);

        return $path;
    }

    public function storeContents(string $contents, string $baseUuid, string $documentUuid, string $extension): string
    {
        $path = $this->directory($baseUuid).'/'.$documentUuid.'.'.preg_replace('/[^a-z0-9]/', '', Str::lower($extension));

        $this->disk()->put($path, $contents, ['visibility' => 'private']);

        return $path;
    }

    /**
     * Copies a stored object to a local temp file for extraction.
     *
     * Extractors take a filesystem path (ZipArchive and the PDF reader both need
     * one), which an S3 object does not have. Callers must delete the temp file;
     * DocumentProcessingService does so in a finally block.
     */
    public function pullToTemporaryFile(KnowledgeDocument $document): string
    {
        $temporary = tempnam(sys_get_temp_dir(), 'kb_');
        $stream = Storage::disk($document->storage_disk ?? $this->diskName())->readStream($document->storage_path);

        if ($stream === null) {
            @unlink($temporary);

            throw new \RuntimeException("Stored file missing for document {$document->uuid}.");
        }

        $handle = fopen($temporary, 'wb');
        stream_copy_to_stream($stream, $handle);
        fclose($handle);
        fclose($stream);

        return $temporary;
    }

    /**
     * A time-limited download URL.
     *
     * S3-compatible disks get a native pre-signed URL. Local disks have no such
     * concept, so a Laravel signed route is used instead — the download
     * controller re-checks the policy on the way through, so the signature is a
     * second factor rather than the only one.
     */
    public function temporaryUrl(KnowledgeDocument $document): string
    {
        $minutes = (int) config('knowledge.storage.signed_url_ttl_minutes');
        $disk = Storage::disk($document->storage_disk ?? $this->diskName());

        if (method_exists($disk, 'temporaryUrl')) {
            try {
                return $disk->temporaryUrl($document->storage_path, now()->addMinutes($minutes));
            } catch (\Throwable) {
                // Local/public drivers throw rather than support this.
            }
        }

        return URL::temporarySignedRoute(
            'tenant.admin.knowledge.documents.download',
            now()->addMinutes($minutes),
            ['document' => $document->uuid]
        );
    }

    public function delete(KnowledgeDocument $document): void
    {
        if (! $document->storage_path) {
            return;
        }

        Storage::disk($document->storage_disk ?? $this->diskName())->delete($document->storage_path);
    }

    /** @param  array<int, string>  $paths */
    public function deletePaths(array $paths, ?string $disk = null): void
    {
        if ($paths) {
            Storage::disk($disk ?? $this->diskName())->delete($paths);
        }
    }

    /** Removes everything belonging to a knowledge base (hard delete). */
    public function deleteBaseDirectory(string $baseUuid): void
    {
        $this->disk()->deleteDirectory($this->directory($baseUuid));
    }

    private function directory(string $baseUuid): string
    {
        $tenantId = tenancy()->initialized ? tenant('id') : 'central';
        $prefix = trim((string) config('knowledge.storage.path_prefix'), '/');

        return "tenants/{$tenantId}/{$prefix}/{$baseUuid}";
    }
}
