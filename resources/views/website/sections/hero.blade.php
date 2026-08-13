@php
    $requestedAlignment = $content['alignment'] ?? 'center';
    $alignment = in_array($requestedAlignment, ['left', 'center', 'right'], true) ? $requestedAlignment : 'center';
    $background = match ($content['background'] ?? 'light') { 'brand' => 'bg-slate-950 text-white', 'muted' => 'bg-slate-50', default => 'bg-white' };
    $alignClass = match ($alignment) { 'left' => 'text-left items-start', 'right' => 'text-right items-end', default => 'text-center items-center' };
@endphp
<section class="{{ $background }}"><div class="cms-container mx-auto flex flex-col px-6 py-20 sm:py-28 {{ $alignClass }}">
    @if(!empty($content['eyebrow']))<p class="text-sm font-bold uppercase tracking-widest text-indigo-500">{{ $content['eyebrow'] }}</p>@endif
    @if(!empty($content['heading']))<h1 class="max-w-4xl text-4xl font-bold tracking-tight sm:text-5xl">{{ $content['heading'] }} @if($content['highlighted_text'] ?? null)<span style="color:var(--cms-accent)">{{ $content['highlighted_text'] }}</span>@endif</h1>@endif
    @if(!empty($content['description']))<p class="mt-5 max-w-2xl text-lg opacity-75">{{ $content['description'] }}</p>@endif
    @if(!empty($content['video_url']))<video controls preload="metadata" class="mt-10 max-h-96 max-w-full rounded-xl border"><source src="{{ $content['video_url'] }}"></video>@elseif(!empty($content['image_url']))<img src="{{ $content['image_url'] }}" alt="" loading="lazy" class="mt-10 max-h-96 max-w-full rounded-xl border shadow-lg">@endif
    <div class="mt-8 flex flex-wrap gap-3 {{ $alignment === 'center' ? 'justify-center' : ($alignment === 'right' ? 'justify-end' : 'justify-start') }}">@if(!empty($content['primary_label']) && !empty($content['primary_url']))<a href="{{ $content['primary_url'] }}" class="cms-button px-6 py-3 text-sm font-bold">{{ $content['primary_label'] }}</a>@endif @if(!empty($content['secondary_label']) && !empty($content['secondary_url']))<a href="{{ $content['secondary_url'] }}" class="rounded-lg border border-slate-300 px-6 py-3 text-sm font-bold">{{ $content['secondary_label'] }}</a>@endif</div>
</div></section>
