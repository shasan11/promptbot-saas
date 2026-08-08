<?php

namespace App\Services\Knowledge\Crawler;

/**
 * Canonicalises URLs so the crawler visits each page once.
 *
 * Without this, `/help`, `/help/`, `/help?utm_source=twitter` and
 * `/help#section` are four different pages: four fetches, four documents, four
 * sets of embeddings, and four near-identical results competing in every
 * retrieval. Normalisation is what makes the crawl page budget mean anything.
 */
class UrlNormaliser
{
    public function normalise(string $url, ?string $relativeTo = null): ?string
    {
        $url = trim($url);

        if ($url === '' || preg_match('#^(mailto|tel|javascript|data):#i', $url)) {
            return null;
        }

        if ($relativeTo !== null && ! preg_match('#^https?://#i', $url)) {
            $url = $this->resolveRelative($url, $relativeTo);

            if ($url === null) {
                return null;
            }
        }

        $parts = parse_url($url);

        if ($parts === false || empty($parts['host']) || empty($parts['scheme'])) {
            return null;
        }

        $scheme = strtolower($parts['scheme']);
        $host = strtolower($parts['host']);
        $port = $parts['port'] ?? null;

        // Default ports are noise; keeping them would make https://a.test and
        // https://a.test:443 distinct pages.
        if (($scheme === 'http' && $port === 80) || ($scheme === 'https' && $port === 443)) {
            $port = null;
        }

        $path = $parts['path'] ?? '/';
        $path = preg_replace('#/{2,}#', '/', $path) ?? $path;

        // Fold the trailing slash, but never on the root — "" is not a path.
        if ($path !== '/' && str_ends_with($path, '/')) {
            $path = rtrim($path, '/');
        }

        $query = $this->cleanQuery($parts['query'] ?? '');

        // The fragment is never sent to the server, so two URLs differing only
        // by fragment are the same page.
        return $scheme.'://'.$host.($port ? ":{$port}" : '').$path.($query !== '' ? "?{$query}" : '');
    }

    /** Stable key for the unique index and for de-duplication sets. */
    public function hash(string $normalisedUrl): string
    {
        return hash('sha256', $normalisedUrl);
    }

    public function sameHost(string $url, string $rootUrl, bool $includeSubdomains = false): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $rootHost = strtolower((string) parse_url($rootUrl, PHP_URL_HOST));

        if ($host === '' || $rootHost === '') {
            return false;
        }

        if ($host === $rootHost) {
            return true;
        }

        // The leading dot matters: it stops "evilexample.com" matching
        // "example.com" as a subdomain.
        return $includeSubdomains && str_ends_with($host, '.'.ltrim($rootHost, '.'));
    }

    /**
     * Drops tracking parameters and sorts the rest, so parameter order does not
     * create distinct URLs.
     */
    private function cleanQuery(string $query): string
    {
        if ($query === '') {
            return '';
        }

        parse_str($query, $params);

        foreach ((array) config('knowledge.crawler.strip_query_parameters') as $tracking) {
            unset($params[$tracking]);
        }

        if (! $params) {
            return '';
        }

        ksort($params);

        return http_build_query($params);
    }

    private function resolveRelative(string $relative, string $base): ?string
    {
        $baseParts = parse_url($base);

        if ($baseParts === false || empty($baseParts['host'])) {
            return null;
        }

        $scheme = $baseParts['scheme'] ?? 'https';
        $authority = $baseParts['host'].(isset($baseParts['port']) ? ':'.$baseParts['port'] : '');

        // Protocol-relative: //cdn.example.com/x
        if (str_starts_with($relative, '//')) {
            return $scheme.':'.$relative;
        }

        if (str_starts_with($relative, '/')) {
            return "{$scheme}://{$authority}".$this->collapseDotSegments($relative);
        }

        $basePath = $baseParts['path'] ?? '/';
        $directory = str_ends_with($basePath, '/') ? $basePath : dirname($basePath).'/';

        return "{$scheme}://{$authority}".$this->collapseDotSegments($directory.$relative);
    }

    /** Resolves ./ and ../ so traversal cannot escape above the root. */
    private function collapseDotSegments(string $path): string
    {
        $segments = [];

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                array_pop($segments);

                continue;
            }

            $segments[] = $segment;
        }

        return '/'.implode('/', $segments);
    }
}
