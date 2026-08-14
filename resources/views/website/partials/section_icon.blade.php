@php
    $iconKey = abs(crc32(strtolower((string) ($title ?? 'feature')))) % 8;
    $iconPaths = [
        '<path d="M4 5h16v11H7l-3 3V5Z"/><path d="M8 9h8M8 12h5"/>',
        '<circle cx="12" cy="8" r="3"/><path d="M6 20v-2a6 6 0 0 1 12 0v2M5 9a3 3 0 0 0 0 6M19 9a3 3 0 0 1 0 6"/>',
        '<path d="M7 3h10v4H7zM5 7h14v14H5zM9 12h6M9 16h4"/>',
        '<path d="m4 14 4-4 4 3 7-8M15 5h4v4"/><path d="M4 20h16"/>',
        '<path d="M12 3 4 7v5c0 5 3.4 8 8 9 4.6-1 8-4 8-9V7l-8-4Z"/><path d="m9 12 2 2 4-4"/>',
        '<path d="M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h6v6h-6z"/>',
        '<circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3M5 5l2 2M17 17l2 2M19 5l-2 2M7 17l-2 2"/>',
        '<path d="M5 4h14v16H5zM8 8h8M8 12h8M8 16h5"/>',
    ];
@endphp
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="{{ $class ?? 'h-5 w-5' }}" aria-hidden="true">{!! $iconPaths[$iconKey] !!}</svg>
