@php
    $requestedAlignment = $content['alignment'] ?? 'left';
    $alignment = in_array($requestedAlignment, ['left', 'center', 'right'], true) ? $requestedAlignment : 'left';
    $innerPage = ($page->slug ?? 'home') !== 'home';
    $dark = (($content['background'] ?? 'light') === 'brand') || $innerPage;
@endphp
<section class="cms-page-hero relative overflow-hidden {{ $dark ? 'bg-slate-950 text-white' : 'bg-white text-slate-950' }}">
    <div class="pointer-events-none absolute inset-0" aria-hidden="true">
        <div class="absolute -right-40 -top-40 h-[34rem] w-[34rem] rounded-full bg-emerald-300/20 blur-3xl"></div><div class="absolute -left-32 bottom-0 h-80 w-80 rounded-full bg-cyan-200/20 blur-3xl"></div>
        <div class="absolute inset-0 opacity-70" style="background-image:linear-gradient(to right,rgba(16,185,129,.10) 1px,transparent 1px),linear-gradient(to bottom,rgba(16,185,129,.10) 1px,transparent 1px),linear-gradient(to right,rgba(16,185,129,.05) 1px,transparent 1px),linear-gradient(to bottom,rgba(16,185,129,.05) 1px,transparent 1px);background-size:44px 44px,44px 44px,176px 176px,176px 176px"></div>
    </div>
    <div class="cms-container relative mx-auto grid items-center px-6 {{ $innerPage ? 'py-8 sm:py-10' : ($alignment === 'center' ? 'gap-8 py-12 sm:py-16 lg:grid-cols-2 lg:py-16' : 'gap-7 py-10 sm:py-12 lg:grid-cols-2 lg:gap-10 lg:py-14') }}">
        <div class="cms-reveal {{ $innerPage ? 'mx-auto w-full max-w-4xl text-center' : ($alignment === 'center' ? 'max-w-5xl text-center lg:col-span-2 lg:mx-auto' : ($alignment === 'right' ? 'text-right' : 'text-left')) }}">
            @if(!empty($content['eyebrow']))<p class="cms-eyebrow">{{ $content['eyebrow'] }}</p>@endif
            @if(!empty($content['heading']))<h1 class="font-bold leading-[1.08] tracking-[-0.035em] {{ $innerPage ? 'mt-2 text-2xl sm:text-3xl lg:text-4xl' : 'text-3xl sm:text-4xl lg:text-5xl '.($alignment === 'center' ? 'mt-4' : 'mt-3') }}">{{ $content['heading'] }} @if($content['highlighted_text'] ?? null)<span class="text-emerald-400">{{ $content['highlighted_text'] }}</span>@endif</h1>@endif
            @if(!empty($content['description']))<p class="max-w-2xl {{ $innerPage ? 'mx-auto mt-3 text-sm leading-6 sm:text-base' : (($alignment === 'center' ? 'mx-auto mt-6 text-lg leading-8' : 'mt-5 text-base leading-7').' sm:text-lg') }} {{ $dark ? 'text-slate-300' : 'text-slate-600' }}">{{ $content['description'] }}</p>@endif
            <div class="flex flex-wrap gap-3 {{ $innerPage ? 'mt-5 justify-center' : ($alignment === 'center' ? 'mt-8 justify-center' : 'mt-6') }} {{ !$innerPage && $alignment === 'right' ? 'justify-end' : '' }}">
                @if(!empty($content['primary_label']) && !empty($content['primary_url']))<a href="{{ $content['primary_url'] }}" class="cms-button inline-flex items-center justify-center px-6 py-3.5 text-sm font-bold shadow-lg shadow-emerald-950/15">{{ $content['primary_label'] }} <span class="ml-2" aria-hidden="true">&rarr;</span></a>@endif
                @if(!empty($content['secondary_label']) && !empty($content['secondary_url']))<a href="{{ $content['secondary_url'] }}" class="inline-flex items-center justify-center rounded-xl border {{ $dark ? 'border-white/20 hover:bg-white/10' : 'border-slate-300 bg-white hover:bg-slate-50' }} px-6 py-3.5 text-sm font-bold">{{ $content['secondary_label'] }}</a>@endif
            </div>
            @if(!$innerPage && $alignment !== 'center')<div class="mt-6 flex flex-wrap gap-x-5 gap-y-2 text-xs font-semibold {{ $dark ? 'text-slate-400' : 'text-slate-500' }}"><span>&#10003; Self-hosted</span><span>&#10003; Multi-tenant</span><span>&#10003; Built for growing support teams</span></div>@endif
        </div>
        @if(!$innerPage && $alignment !== 'center')
            <div class="cms-reveal relative" style="animation-delay:120ms">
                @if(!empty($content['video_url']))
                    <video controls preload="metadata" class="w-full rounded-2xl border border-slate-200/20 shadow-2xl"><source src="{{ $content['video_url'] }}"></video>
                @elseif(!empty($content['image_url']))
                    <img src="{{ $content['image_url'] }}" alt="PromptBot customer support workspace" class="w-full rounded-2xl border border-slate-200/40 shadow-2xl">
                @else
                    <div class="cms-product-window cms-float" role="img" aria-label="Representative PromptBot unified inbox interface">
                        <div class="flex items-center justify-between border-b border-slate-200 bg-white px-4 py-3"><div class="flex gap-1.5"><i></i><i></i><i></i></div><span class="text-[10px] font-bold uppercase tracking-[.2em] text-slate-400">Support workspace</span><span class="h-7 w-7 rounded-full bg-emerald-100"></span></div>
                        <div class="grid min-h-[320px] grid-cols-[58px_0.8fr_1.25fr] bg-slate-50 sm:grid-cols-[68px_0.8fr_1.25fr]">
                            <div class="border-r border-slate-200 bg-slate-950 p-3"><div class="mx-auto h-8 w-8 rounded-lg bg-emerald-500"></div><div class="mt-8 space-y-4">@foreach(range(1,5) as $i)<div class="mx-auto h-7 w-7 rounded-lg {{ $i === 1 ? 'bg-white/15' : 'bg-white/5' }}"></div>@endforeach</div></div>
                            <div class="border-r border-slate-200 bg-white p-3"><p class="text-xs font-bold text-slate-900">Unified inbox</p><div class="mt-4 space-y-2">@foreach(['Payment question','Setup help','SLA follow-up','Account access'] as $i => $label)<div class="rounded-lg border p-2 {{ $i === 0 ? 'border-emerald-200 bg-emerald-50' : 'border-slate-100' }}"><div class="flex items-center gap-2"><span class="h-6 w-6 rounded-full bg-slate-200"></span><span class="truncate text-[10px] font-bold text-slate-700">{{ $label }}</span></div><div class="mt-2 h-1.5 rounded bg-slate-100"></div></div>@endforeach</div></div>
                            <div class="p-4"><div class="flex items-start justify-between"><div><p class="text-xs font-bold text-slate-900">Payment question</p><p class="mt-1 text-[9px] text-slate-400">Customer context and conversation</p></div><span class="rounded-full bg-emerald-100 px-2 py-1 text-[9px] font-bold text-emerald-700">SLA on track</span></div><div class="mt-8 space-y-4"><div class="mr-8 rounded-xl bg-white p-3 text-[10px] leading-4 text-slate-600 shadow-sm">Can you help me understand the latest invoice?</div><div class="ml-8 rounded-xl bg-emerald-600 p-3 text-[10px] leading-4 text-white">Absolutely. I’m reviewing the account context now.</div></div><div class="mt-12 grid grid-cols-2 gap-2"><div class="rounded-lg border bg-white p-3"><p class="text-[9px] text-slate-400">Owner</p><p class="mt-1 text-[10px] font-bold">Billing team</p></div><div class="rounded-lg border bg-white p-3"><p class="text-[9px] text-slate-400">Priority</p><p class="mt-1 text-[10px] font-bold">Normal</p></div></div></div>
                        </div>
                    </div>
                @endif
            </div>
        @endif
    </div>
</section>
