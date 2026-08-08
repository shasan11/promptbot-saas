<?php

namespace App\Contracts\Knowledge;

use App\Models\Knowledge\KnowledgeSource;

/**
 * The extension point for external knowledge systems (Google Drive, Notion,
 * Confluence, S3, a customer's own API…).
 *
 * Nothing downstream of `fetch()` knows or cares which provider produced an
 * item: discovery yields descriptors, fetch yields bytes or text, and the same
 * extraction → chunking → embedding pipeline runs regardless. Adding a provider
 * therefore means implementing this interface and registering it — not touching
 * the processing code.
 */
interface KnowledgeConnectorInterface
{
    public function key(): string;

    public function displayName(): string;

    /** Establishes a session from the source's stored credentials. */
    public function connect(KnowledgeSource $source): void;

    /**
     * Cheap liveness check against the remote system. Called before a sync so
     * an expired token parks the source in `attention_required` rather than
     * producing a confusing mid-sync failure.
     */
    public function validateCredentials(KnowledgeSource $source): bool;

    /**
     * Lists remote items in scope, without downloading their contents.
     *
     * Descriptors must include a stable `external_id` and a `content_hash` or
     * `modified_at`, so an unchanged item can be skipped without paying to
     * download and re-embed it.
     *
     * @return iterable<int, array{external_id: string, title: string, mime_type?: string, content_hash?: string, modified_at?: string, url?: string}>
     */
    public function discover(KnowledgeSource $source): iterable;

    /**
     * Retrieves one item's content.
     *
     * @param  array<string, mixed>  $descriptor  As returned by discover().
     * @return array{contents: string, mime_type: string, filename: string}
     */
    public function fetch(KnowledgeSource $source, array $descriptor): array;

    public function disconnect(KnowledgeSource $source): void;
}
