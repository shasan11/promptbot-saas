@php
    $variant = match ($content['variant'] ?? 'brand') {
        'dark' => 'bg-slate-950 text-white',
        'light' => 'border-b border-slate-200 bg-slate-50 text-slate-700',
        default => 'bg-emerald-600 text-white',
    };
@endphp
@if(!empty($content['message']))
<aside class="{{ $variant }}" aria-label="Announcement">
    <div class="cms-container mx-auto flex min-h-10 flex-col items-center justify-center gap-1 px-6 py-2 text-center text-sm font-medium sm:flex-row sm:gap-2">
        <span>{{ $content['message'] }}</span>
        @if(!empty($content['link_label']) && !empty($content['link_url']))
            <a href="{{ $content['link_url'] }}" class="font-bold underline decoration-white/40 underline-offset-4 hover:decoration-current">{{ $content['link_label'] }} <span aria-hidden="true">&rarr;</span></a>
        @endif
    </div>
</aside>
@endif
