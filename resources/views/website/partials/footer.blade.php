@php
    $footerNavigation = $footerNavigation ?? $navigation->where('menu_group', 'footer')->whereNull('parent_id');
@endphp
<footer id="site-footer" class="relative overflow-hidden border-t border-white/10 bg-slate-950 text-slate-300">
    <div class="pointer-events-none absolute inset-0 opacity-35" aria-hidden="true" style="background-image:linear-gradient(rgba(16,185,129,.16) 1px,transparent 1px),linear-gradient(90deg,rgba(16,185,129,.16) 1px,transparent 1px);background-size:40px 40px"></div>
    <div class="pointer-events-none absolute -left-24 bottom-0 h-72 w-72 rounded-full bg-emerald-500/10 blur-3xl" aria-hidden="true"></div>
    <div class="pointer-events-none absolute -right-24 top-0 h-72 w-72 rounded-full bg-cyan-400/10 blur-3xl" aria-hidden="true"></div>

    <div class="cms-container relative mx-auto px-6 pb-6 pt-8 sm:pt-10">
        <div class="relative overflow-hidden rounded-2xl border border-emerald-300/20 bg-gradient-to-r from-emerald-950/90 via-emerald-900/75 to-slate-900 px-5 py-5 shadow-2xl shadow-black/20 sm:px-7 lg:flex lg:items-center lg:justify-between lg:gap-8">
            <div class="pointer-events-none absolute inset-0 opacity-30" aria-hidden="true" style="background-image:linear-gradient(rgba(110,231,183,.16) 1px,transparent 1px),linear-gradient(90deg,rgba(110,231,183,.16) 1px,transparent 1px);background-size:28px 28px"></div>
            <div class="relative flex items-start gap-4">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-emerald-300/20 bg-emerald-300/10 text-emerald-200" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-5 w-5"><path d="M7 8h10M7 12h6m-8 7 2.7-2H18a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v9a2 2 0 0 0 1 1.73V19Z" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </span>
                <div>
                    <p class="text-[10px] font-extrabold uppercase tracking-[.18em] text-emerald-300">Ready when you are</p>
                    <h2 class="mt-1 text-lg font-bold tracking-tight text-white sm:text-xl">Bring your support operation into one clear workspace.</h2>
                    <p class="mt-1 max-w-2xl text-xs leading-5 text-emerald-50/65 sm:text-sm">Start with the essentials, then add the workflows your team needs as you grow.</p>
                </div>
            </div>
            <div class="relative mt-5 flex shrink-0 flex-wrap gap-2 lg:mt-0 lg:justify-end">
                <a href="{{ route('portal.register') }}" class="inline-flex items-center justify-center rounded-xl px-4 py-2.5 text-xs font-bold text-white shadow-lg transition hover:-translate-y-0.5 hover:brightness-110" style="background:var(--cms-accent, #059669)">Start free <span class="ml-2" aria-hidden="true">&rarr;</span></a>
                <a href="{{ url('/contact') }}" class="inline-flex items-center justify-center rounded-xl border border-white/15 bg-white/5 px-4 py-2.5 text-xs font-bold text-white transition hover:bg-white/10">Talk to us</a>
            </div>
        </div>

        <div class="grid gap-x-6 gap-y-8 py-9 sm:grid-cols-2 lg:grid-cols-5">
            <div class="sm:col-span-2 lg:col-span-1">
                @if($darkLogo)<img src="{{ $darkLogo }}" alt="{{ $siteName }}" class="h-9 w-auto max-w-[180px] object-contain">@else<p class="text-xl font-bold text-white">{{ $siteName }}</p>@endif
                <p class="mt-4 max-w-sm text-[13px] leading-6 text-slate-400">{{ $settings['footer_description'] ?? 'Clear, connected customer support operations.' }}</p>
                <div class="mt-5 flex flex-wrap gap-2 text-[10px] font-bold uppercase tracking-wider text-slate-400">
                    <span class="rounded-full border border-white/10 bg-white/5 px-3 py-1.5">Self-hosted</span>
                    <span class="rounded-full border border-white/10 bg-white/5 px-3 py-1.5">Multi-tenant</span>
                    <span class="rounded-full border border-white/10 bg-white/5 px-3 py-1.5">Omnichannel</span>
                </div>
            </div>

            @if($footerLinks->count())
                <div class="grid grid-cols-2 gap-x-6 gap-y-7 sm:col-span-2 sm:grid-cols-4 lg:contents">
                    @foreach($footerLinks as $group => $links)
                        <div>
                            <p class="text-[10px] font-extrabold uppercase tracking-[.16em] text-emerald-300/80">{{ $group }}</p>
                            <ul class="mt-3 space-y-2 text-[13px]">
                                @foreach($links as $link)<li><a href="{{ $link->url }}" class="inline-flex items-center gap-1 text-slate-400 transition hover:translate-x-0.5 hover:text-white">{{ $link->label }}</a></li>@endforeach
                            </ul>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="flex flex-col gap-3 border-t border-white/10 py-5 text-[11px] text-slate-500 sm:flex-row sm:items-center sm:justify-between">
            <span>{{ $settings['copyright_text'] ?? ('© '.date('Y').' '.$siteName.'. All rights reserved.') }}</span>
            <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
                @foreach($footerNavigation as $item)<a href="{{ $item->url }}" @if($item->open_new_tab) target="_blank" rel="noopener noreferrer" @endif class="transition hover:text-white">{{ $item->label }}</a>@endforeach
                @if($settings['contact_email'] ?? null)<a href="mailto:{{ $settings['contact_email'] }}" class="transition hover:text-white">{{ $settings['contact_email'] }}</a>@endif
                @if($settings['social_twitter'] ?? null)<a href="{{ $settings['social_twitter'] }}" class="transition hover:text-white">Twitter/X</a>@endif
                @if($settings['social_linkedin'] ?? null)<a href="{{ $settings['social_linkedin'] }}" class="transition hover:text-white">LinkedIn</a>@endif
                <a href="#" class="inline-flex items-center gap-1.5 text-slate-400 transition hover:text-white"><span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span> Back to top</a>
            </div>
        </div>
    </div>
</footer>
