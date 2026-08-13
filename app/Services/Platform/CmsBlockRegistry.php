<?php

namespace App\Services\Platform;

use Illuminate\Support\Arr;
use InvalidArgumentException;

class CmsBlockRegistry
{
    public function definitions(): array
    {
        return collect(config('cms.blocks', []))->map(fn (array $definition, string $key) => [
            'key' => $key, 'label' => $definition['label'], 'category' => $definition['category'],
            'icon' => $definition['icon'] ?? 'layout-template',
            'defaults' => $definition['defaults'],
            'schema' => collect($definition['defaults'])->map(fn (mixed $value) => match (true) {
                is_bool($value) => 'boolean', is_array($value) => 'array', is_numeric($value) => 'number', default => 'string',
            })->all(),
            'editor' => $definition['editor'] ?? 'structured',
            'renderer' => $definition['renderer'] ?? "website.sections.{$key}",
            'permission' => $definition['permission'] ?? null,
        ])->values()->all();
    }

    public function sanitize(string $type, array $content): array
    {
        $definition = config("cms.blocks.{$type}");
        if (! $definition) throw new InvalidArgumentException("Unknown CMS block type: {$type}");
        $allowed = array_keys($definition['defaults']);
        $content = Arr::only($content, $allowed);

        foreach ($content as $key => $value) {
            if ($key === 'html') {
                $content[$key] = $this->sanitizeHtml((string) $value);
            } elseif (str_ends_with($key, '_url') || $key === 'url') {
                $content[$key] = $this->sanitizeUrl((string) $value);
            } else {
                $content[$key] = $this->sanitizeValue($value, $key);
            }
        }

        return [...$definition['defaults'], ...$content];
    }

    private function sanitizeValue(mixed $value, string|int|null $key = null): mixed
    {
        if (is_array($value)) {
            $value = array_slice($value, 0, 100, true);
            foreach ($value as $nestedKey => $item) $value[$nestedKey] = $this->sanitizeValue($item, $nestedKey);
            return $value;
        }
        if (is_string($value) && (str_ends_with((string) $key, '_url') || $key === 'url')) return $this->sanitizeUrl($value);
        if (is_string($value)) return str(trim(strip_tags($value)))->limit(10000, '')->toString();
        return $value;
    }

    private function sanitizeUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '' || str_starts_with($url, '/') || str_starts_with($url, '#')) return $url;
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        return in_array($scheme, ['http', 'https', 'mailto', 'tel'], true) ? $url : '';
    }

    private function sanitizeHtml(string $html): string
    {
        $html = str($html)->limit(200000, '')->toString();
        $html = preg_replace('#<(script|style|iframe|object|embed)[^>]*>.*?</\1>#is', '', $html) ?? '';
        $html = preg_replace('/\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html) ?? '';
        $html = preg_replace('/(href|src)\s*=\s*(["\'])\s*javascript:[^"\']*\2/i', '$1="#"', $html) ?? '';

        return strip_tags($html, '<p><br><strong><em><b><i><u><s><a><ul><ol><li><h2><h3><h4><blockquote><code><pre><span>');
    }
}
