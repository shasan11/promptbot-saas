<?php

namespace App\Services\Knowledge;

use App\Models\Knowledge\KnowledgeArticle;
use App\Models\Knowledge\KnowledgeArticleVersion;
use App\Models\Knowledge\KnowledgeDocument;
use App\Models\Knowledge\KnowledgeDocumentVersion;
use App\Models\Knowledge\KnowledgeFaq;
use App\Models\Knowledge\KnowledgeFaqVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Version history for documents and FAQs.
 *
 * Historical *text* is retained so changes can be reviewed, compared and rolled
 * back. Historical *embeddings* are not: keeping a vector set per version would
 * multiply the largest table in the schema by the edit count, and restoring a
 * version simply re-runs the pipeline, which is cheap by comparison.
 */
class KnowledgeVersionService
{
    /**
     * Records the document's current state as a version, before it is
     * overwritten.
     */
    public function snapshot(KnowledgeDocument $document, ?string $changeSummary = null, ?User $actor = null): KnowledgeDocumentVersion
    {
        return DB::transaction(function () use ($document, $changeSummary, $actor) {
            // Locked and derived from MAX rather than the document's counter:
            // two concurrent replacements would otherwise both claim the same
            // number and collide on the unique index.
            $latest = (int) KnowledgeDocumentVersion::query()
                ->where('knowledge_document_id', $document->id)
                ->lockForUpdate()
                ->max('version_number');

            $previous = KnowledgeDocumentVersion::query()
                ->where('knowledge_document_id', $document->id)
                ->where('is_active', true)
                ->first();

            $version = KnowledgeDocumentVersion::create([
                'knowledge_document_id' => $document->id,
                'version_number' => $latest + 1,
                'previous_version_id' => $previous?->id,
                'title' => $document->title,
                'original_filename' => $document->original_filename,
                'storage_disk' => $document->storage_disk,
                'storage_path' => $document->storage_path,
                'file_size' => $document->file_size,
                'checksum' => $document->checksum,
                'extracted_text' => KnowledgeDocument::query()->whereKey($document->id)->value('extracted_text'),
                'content_hash' => $document->content_hash,
                'character_count' => $document->character_count,
                'change_summary' => $changeSummary,
                'is_active' => false,
                'created_by' => $actor?->id ?? $document->updated_by,
            ]);

            $document->forceFill(['version_number' => $latest + 1])->save();

            return $version;
        });
    }

    /**
     * Promotes a version to active once its content is indexed.
     *
     * The swap happens after the new content is ready, never before, so there is
     * no window where the document is live but unsearchable.
     */
    public function activate(KnowledgeDocument $document, KnowledgeDocumentVersion $version): void
    {
        DB::transaction(function () use ($document, $version): void {
            KnowledgeDocumentVersion::query()
                ->where('knowledge_document_id', $document->id)
                ->update(['is_active' => false]);

            $version->forceFill(['is_active' => true])->save();
            $document->forceFill(['active_version_id' => $version->id])->save();
        });
    }

    /**
     * Restores a previous version's content onto the document and returns it
     * ready for re-processing. The current state is snapshotted first, so a
     * restore is itself reversible.
     */
    public function restore(KnowledgeDocument $document, KnowledgeDocumentVersion $version, ?User $actor = null): KnowledgeDocument
    {
        $this->snapshot($document, "Superseded by restore of version {$version->version_number}", $actor);

        $document->forceFill([
            'title' => $version->title,
            'original_filename' => $version->original_filename,
            'storage_disk' => $version->storage_disk,
            'storage_path' => $version->storage_path,
            'file_size' => $version->file_size,
            'checksum' => $version->checksum,
            'extracted_text' => KnowledgeDocumentVersion::query()->whereKey($version->id)->value('extracted_text'),
            // Cleared so the pipeline treats the restored text as changed and
            // re-chunks it, rather than short-circuiting on an equal hash.
            'content_hash' => null,
            'updated_by' => $actor?->id,
        ])->save();

        return $document;
    }

    public function snapshotFaq(KnowledgeFaq $faq, ?string $changeSummary = null, ?User $actor = null): KnowledgeFaqVersion
    {
        return DB::transaction(function () use ($faq, $changeSummary, $actor) {
            $latest = (int) KnowledgeFaqVersion::query()
                ->where('knowledge_faq_id', $faq->id)
                ->lockForUpdate()
                ->max('version_number');

            $version = KnowledgeFaqVersion::create([
                'knowledge_faq_id' => $faq->id,
                'version_number' => $latest + 1,
                'question' => $faq->question,
                'answer' => $faq->answer,
                'change_summary' => $changeSummary,
                'created_by' => $actor?->id,
            ]);

            $faq->forceFill(['version_number' => $latest + 1])->save();

            return $version;
        });
    }

    public function restoreFaq(KnowledgeFaq $faq, KnowledgeFaqVersion $version, ?User $actor = null): KnowledgeFaq
    {
        $this->snapshotFaq($faq, "Superseded by restore of version {$version->version_number}", $actor);

        $faq->forceFill([
            'question' => $version->question,
            'answer' => $version->answer,
            'content_hash' => null,
            'updated_by' => $actor?->id,
        ])->save();

        return $faq;
    }

    public function snapshotArticle(KnowledgeArticle $article, ?string $changeSummary = null, ?User $actor = null): KnowledgeArticleVersion
    {
        return DB::transaction(function () use ($article, $changeSummary, $actor) {
            $latest = (int) KnowledgeArticleVersion::query()
                ->where('knowledge_article_id', $article->id)
                ->lockForUpdate()
                ->max('version_number');

            $version = KnowledgeArticleVersion::create([
                'knowledge_article_id' => $article->id,
                'version_number' => $latest + 1,
                'title' => $article->title,
                'summary' => $article->summary,
                'body' => $article->body,
                'status' => $article->status->value,
                'change_summary' => $changeSummary,
                'created_by' => $actor?->id,
            ]);

            $article->forceFill(['version_number' => $latest + 1])->save();

            return $version;
        });
    }

    /**
     * Restores a version's title/summary/body onto the article, leaving its
     * status untouched — a restore is an editorial correction of wording, not
     * an approval, so it must not itself make a draft answerable again.
     */
    public function restoreArticle(KnowledgeArticle $article, KnowledgeArticleVersion $version, ?User $actor = null): KnowledgeArticle
    {
        $this->snapshotArticle($article, "Superseded by restore of version {$version->version_number}", $actor);

        $article->forceFill([
            'title' => $version->title,
            'summary' => $version->summary,
            'body' => $version->body,
            'content_hash' => null,
            'updated_by' => $actor?->id,
        ])->save();

        return $article;
    }

    /**
     * A line-level diff between two versions, for the compare view.
     *
     * @return array<int, array{type: string, text: string}>
     */
    public function diff(KnowledgeDocumentVersion $from, KnowledgeDocumentVersion $to): array
    {
        $fromLines = preg_split('/\n/', (string) KnowledgeDocumentVersion::query()->whereKey($from->id)->value('extracted_text')) ?: [];
        $toLines = preg_split('/\n/', (string) KnowledgeDocumentVersion::query()->whereKey($to->id)->value('extracted_text')) ?: [];

        // Bounded: a full LCS over two 50,000-line documents is not something to
        // run inside a web request. The compare view shows the first slice and
        // says so.
        $limit = 2000;
        $fromLines = array_slice($fromLines, 0, $limit);
        $toLines = array_slice($toLines, 0, $limit);

        $removed = array_diff($fromLines, $toLines);
        $added = array_diff($toLines, $fromLines);

        $diff = [];

        foreach ($toLines as $index => $line) {
            $type = match (true) {
                in_array($line, $added, true) => 'added',
                default => 'unchanged',
            };

            $diff[] = ['type' => $type, 'text' => $line];

            if (isset($fromLines[$index]) && in_array($fromLines[$index], $removed, true)) {
                $diff[] = ['type' => 'removed', 'text' => $fromLines[$index]];
            }
        }

        return $diff;
    }
}
