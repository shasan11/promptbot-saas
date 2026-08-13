@php
    $headerNavigation = $navigation->where('menu_group', 'header')->whereNull('parent_id');
    $mobileNavigation = $navigation->where('menu_group', 'mobile')->whereNull('parent_id');
    if ($mobileNavigation->isEmpty()) $mobileNavigation = $headerNavigation;
    $footerNavigation = $navigation->where('menu_group', 'footer')->whereNull('parent_id');
    $metaDescription = $page->seo['description'] ?? ($settings['default_description'] ?? null);
    $ogImage = $page->open_graph['image'] ?? ($settings['default_og_image'] ?? null);
    $bodyFont = ($settings['body_font'] ?? null) ?: 'Inter';
    $headingFont = ($settings['heading_font'] ?? null) ?: $bodyFont;
    $siteName = $settings['site_name'] ?? config('app.name');
    $titleFormat = ($settings['default_meta_title_format'] ?? null) ?: '{title} · {site_name}';
    $documentTitle = str_replace(['{title}', '{site_name}'], [$page->seo['title'] ?? $page->title, $siteName], $titleFormat);
    $canonicalUrl = $page->canonical_url ?: (($settings['canonical_base_url'] ?? null) ? rtrim($settings['canonical_base_url'], '/').($page->slug === 'home' ? '/' : '/'.$page->slug) : ($page->slug === 'home' ? url('/') : url('/'.$page->slug)));
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $documentTitle }}</title>
        @if($metaDescription)
            <meta name="description" content="{{ $metaDescription }}">
        @endif
        <link rel="canonical" href="{{ $canonicalUrl }}">
        <meta name="robots" content="{{ $page->robots_index ? 'index' : 'noindex' }},{{ $page->robots_follow ? 'follow' : 'nofollow' }}">
        <meta property="og:title" content="{{ $page->open_graph['title'] ?? $page->seo['title'] ?? $page->title }}">
        @if($page->open_graph['description'] ?? $metaDescription)<meta property="og:description" content="{{ $page->open_graph['description'] ?? $metaDescription }}">@endif
        @if($ogImage)<meta property="og:image" content="{{ $ogImage }}">@endif
        <meta property="og:url" content="{{ $canonicalUrl }}">
        <meta property="og:type" content="website">
        <meta name="twitter:card" content="{{ $settings['twitter_card_type'] ?? 'summary_large_image' }}">
        <meta name="twitter:title" content="{{ $page->twitter['title'] ?? $page->open_graph['title'] ?? $page->title }}">
        @if($page->twitter['description'] ?? null)<meta name="twitter:description" content="{{ $page->twitter['description'] }}">@endif
        @if($page->twitter['image'] ?? null)<meta name="twitter:image" content="{{ $page->twitter['image'] }}">@endif
        @if($page->schema_json)<script type="application/ld+json">{!! json_encode($page->schema_json, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) !!}</script>@endif
        @if($settings['google_verification'] ?? null)<meta name="google-site-verification" content="{{ $settings['google_verification'] }}">@endif
        @if($settings['bing_verification'] ?? null)<meta name="msvalidate.01" content="{{ $settings['bing_verification'] }}">@endif
        @if($settings['favicon_url'] ?? null)<link rel="icon" href="{{ $settings['favicon_url'] }}">@endif
        @if($settings['google_analytics_id'] ?? null)
            <script async src="https://www.googletagmanager.com/gtag/js?id={{ $settings['google_analytics_id'] }}"></script>
            <script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments)}gtag('js',new Date());gtag('config',@json($settings['google_analytics_id']));</script>
        @endif
        @if($settings['google_tag_manager_id'] ?? null)
            <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f)})(window,document,'script','dataLayer',@json($settings['google_tag_manager_id']));</script>
        @endif
        @if($settings['meta_pixel_id'] ?? null)
            <script>!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');fbq('init',@json($settings['meta_pixel_id']));fbq('track','PageView');</script>
        @endif

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700|manrope:400,500,600,700|poppins:400,500,600,700|roboto:400,500,600,700&display=swap" rel="stylesheet" />
        @vite('resources/css/app.css')
        <style>
            :root { --cms-primary: {{ $settings['primary_color'] ?? '#0f172a' }}; --cms-secondary: {{ $settings['secondary_color'] ?? '#475569' }}; --cms-accent: {{ $settings['accent_color'] ?? '#4f46e5' }}; --cms-button-radius: {{ $settings['button_radius'] ?? '8px' }}; --cms-card-radius: {{ $settings['card_radius'] ?? '12px' }}; --cms-container: {{ $settings['container_width'] ?? '1152px' }}; }
            body { font-family: '{{ $bodyFont }}', ui-sans-serif, system-ui, sans-serif; }
            h1, h2, h3, h4, h5, h6 { font-family: '{{ $headingFont }}', ui-sans-serif, system-ui, sans-serif; }
            .cms-container { max-width: var(--cms-container); }
            .cms-button { border-radius: var(--cms-button-radius); background: var(--cms-primary); color: white; }
        </style>
    </head>
    <body class="min-h-screen bg-white text-slate-700 antialiased">
        @if($settings['google_tag_manager_id'] ?? null)<noscript><iframe src="https://www.googletagmanager.com/ns.html?id={{ $settings['google_tag_manager_id'] }}" height="0" width="0" style="display:none;visibility:hidden" title="Google Tag Manager"></iframe></noscript>@endif
        @if($settings['meta_pixel_id'] ?? null)<noscript><img height="1" width="1" style="display:none" alt="" src="https://www.facebook.com/tr?id={{ $settings['meta_pixel_id'] }}&ev=PageView&noscript=1"></noscript>@endif
        <header class="border-b border-slate-200">
            <div class="cms-container mx-auto flex flex-wrap items-center justify-between gap-4 px-6 py-4">
                <a href="{{ url('/') }}" class="flex items-center gap-2 text-lg font-bold text-navy-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-600 focus-visible:ring-offset-2 rounded">
                    @if($settings['logo_url'] ?? null)
                        <img src="{{ $settings['logo_url'] }}" alt="{{ $settings['site_name'] ?? '' }}" class="h-8 w-auto">
                    @endif
                    {{ $settings['site_name'] ?? config('app.name') }}
                </a>

                @if($headerNavigation->count())
                    <nav class="hidden flex-wrap items-center gap-6 text-sm font-semibold text-slate-600 md:flex" aria-label="Primary">
                        @foreach($headerNavigation as $item)
                            @php
                                $children = $navigation->where('parent_id', $item->id);
                            @endphp
                            @if($item->type === 'dropdown' && $children->count())
                                <details class="relative">
                                    <summary class="cursor-pointer list-none rounded hover:text-navy-900 focus-visible:outline-none focus-visible:ring-2 [&::-webkit-details-marker]:hidden">{{ $item->label }} <span aria-hidden="true">&#9662;</span></summary>
                                    <div class="absolute left-0 z-20 mt-2 min-w-52 rounded-xl border border-slate-200 bg-white p-2 shadow-lg">
                                        @foreach($children as $child)
                                            <a href="{{ $child->url }}" @if($child->open_new_tab) target="_blank" rel="noopener noreferrer" @endif class="block rounded-lg px-3 py-2 hover:bg-slate-50 hover:text-navy-900">{{ $child->label }}</a>
                                        @endforeach
                                    </div>
                                </details>
                            @else
                                <a href="{{ $item->url }}" @if($item->open_new_tab) target="_blank" rel="noopener noreferrer" @endif class="{{ $item->style === 'button' || $item->type === 'button' ? 'cms-button px-4 py-2' : 'rounded hover:text-navy-900' }} focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-600">{{ $item->label }}</a>
                            @endif
                        @endforeach
                    </nav>
                @endif

                <div class="hidden items-center gap-2 md:flex">
                    <a href="{{ route('portal.login') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">Customer login</a>
                    <a href="{{ route('portal.register') }}" class="rounded-md bg-slate-950 px-4 py-2 text-sm font-semibold text-white">Start free trial</a>
                </div>

                @if($mobileNavigation->count())
                    <details class="relative md:hidden">
                        <summary class="flex h-9 w-9 cursor-pointer list-none items-center justify-center rounded-md border border-slate-300 text-slate-600 [&::-webkit-details-marker]:hidden" aria-label="Open menu">
                            <span aria-hidden="true">&#9776;</span>
                        </summary>
                        <nav class="absolute right-0 z-20 mt-2 w-56 rounded-md border border-slate-200 bg-white p-2 shadow-soft-lg" aria-label="Mobile">
                            @foreach($mobileNavigation as $item)
                                <a href="{{ $item->url }}" @if($item->open_new_tab) target="_blank" rel="noopener noreferrer" @endif class="block rounded px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">{{ $item->label }}</a>
                                @foreach($navigation->where('parent_id', $item->id) as $child)
                                    <a href="{{ $child->url }}" @if($child->open_new_tab) target="_blank" rel="noopener noreferrer" @endif class="block rounded py-2 pl-6 pr-3 text-sm text-slate-600 hover:bg-slate-50">{{ $child->label }}</a>
                                @endforeach
                            @endforeach
                            <a href="{{ route('portal.login') }}" class="mt-1 block rounded border-t border-slate-100 px-3 py-2 pt-3 text-sm font-medium text-slate-700 hover:bg-slate-50">Customer login</a>
                            <a href="{{ route('portal.register') }}" class="block rounded px-3 py-2 text-sm font-semibold text-indigo-700 hover:bg-indigo-50">Start free trial</a>
                        </nav>
                    </details>
                @endif
            </div>
        </header>

        <main>
            @if($preview)<div class="bg-amber-100 px-4 py-2 text-center text-sm font-semibold text-amber-900">Draft preview · this page is not publicly published</div>@endif
            @foreach($page->sections as $section)
                @php
                    $blockDefaults = config("cms.blocks.{$section->type}.defaults", []);
                    $blockContent = array_replace($blockDefaults, is_array($section->content) ? $section->content : []);
                @endphp
                @includeFirst(["website.sections.{$section->type}", 'website.sections.generic'], ['content' => $blockContent, 'type' => $section->type])
            @endforeach
        </main>

        <footer class="border-t border-slate-200 bg-slate-50">
            <div class="cms-container mx-auto px-6 py-10">
                @if($settings['footer_description'] ?? null)<p class="mb-8 max-w-xl text-sm leading-6 text-slate-600">{{ $settings['footer_description'] }}</p>@endif
                @if($footerLinks->count())
                    <div class="grid gap-8 sm:grid-cols-3">
                        @foreach($footerLinks as $group => $links)
                            <div>
                                <div class="text-xs font-bold uppercase tracking-wide text-slate-500">{{ $group }}</div>
                                <ul class="mt-3 space-y-2 text-sm">
                                    @foreach($links as $link)
                                        <li><a href="{{ $link->url }}" class="rounded text-slate-600 hover:text-navy-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-600">{{ $link->label }}</a></li>
                                    @endforeach
                                </ul>
                            </div>
                        @endforeach
                    </div>
                @endif
                @if($footerNavigation->count())
                    <nav class="mt-8 flex flex-wrap gap-x-5 gap-y-2 text-sm" aria-label="Footer">
                        @foreach($footerNavigation as $item)<a href="{{ $item->url }}" @if($item->open_new_tab) target="_blank" rel="noopener noreferrer" @endif class="text-slate-600 hover:text-navy-900">{{ $item->label }}</a>@endforeach
                    </nav>
                @endif
                <div class="mt-8 flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 pt-6 text-xs text-slate-500">
                    <span>&copy; {{ date('Y') }} {{ $settings['copyright_text'] ?? (($settings['site_name'] ?? config('app.name')).'. All rights reserved.') }}</span>
                    <div class="flex gap-4">
                        @if($settings['contact_email'] ?? null)<a href="mailto:{{ $settings['contact_email'] }}" class="hover:text-navy-900">{{ $settings['contact_email'] }}</a>@endif
                        @if($settings['social_twitter'] ?? null)<a href="{{ $settings['social_twitter'] }}" class="hover:text-navy-900">Twitter/X</a>@endif
                        @if($settings['social_linkedin'] ?? null)<a href="{{ $settings['social_linkedin'] }}" class="hover:text-navy-900">LinkedIn</a>@endif
                    </div>
                </div>
            </div>
        </footer>
    </body>
</html>
