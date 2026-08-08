<?php

namespace App\Enums\Knowledge;

enum SourceType: string
{
    case File = 'file';
    case Website = 'website';
    case WebsiteCrawl = 'website_crawl';
    case Sitemap = 'sitemap';
    case Faq = 'faq';
    case ManualText = 'manual_text';
    case Integration = 'integration';
    case Api = 'api';
    case Database = 'database';
    case ExternalStorage = 'external_storage';

    public function label(): string
    {
        return match ($this) {
            self::File => 'Uploaded files',
            self::Website => 'Website URL',
            self::WebsiteCrawl => 'Website crawl',
            self::Sitemap => 'Sitemap',
            self::Faq => 'FAQ',
            self::ManualText => 'Manual text',
            self::Integration => 'Integration',
            self::Api => 'API',
            self::Database => 'Database',
            self::ExternalStorage => 'External storage',
        };
    }

    /** Source types whose content lives on remote systems and can be re-fetched. */
    public function isSyncable(): bool
    {
        return in_array($this, [
            self::Website, self::WebsiteCrawl, self::Sitemap,
            self::Integration, self::Api, self::Database, self::ExternalStorage,
        ], true);
    }

    /** Source types that fan out into many `knowledge_website_pages` rows. */
    public function isCrawlable(): bool
    {
        return in_array($this, [self::Website, self::WebsiteCrawl, self::Sitemap], true);
    }
}
