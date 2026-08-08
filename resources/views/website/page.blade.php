<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $page->seo['title'] ?? $page->title }} @if($settings['site_name'] ?? null) &middot; {{ $settings['site_name'] }} @endif</title>
        @if($page->seo['description'] ?? null)
            <meta name="description" content="{{ $page->seo['description'] }}">
        @endif

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />
        @vite('resources/css/app.css')
    </head>
    <body class="min-h-screen bg-white font-sans text-slate-700 antialiased" style="--brand: {{ $settings['primary_color'] ?? '#0f172a' }}">
        <header class="border-b border-slate-200">
            <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-4 px-6 py-4">
                <a href="{{ url('/') }}" class="flex items-center gap-2 text-lg font-bold text-navy-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-600 focus-visible:ring-offset-2 rounded">
                    @if($settings['logo_url'] ?? null)
                        <img src="{{ $settings['logo_url'] }}" alt="{{ $settings['site_name'] ?? '' }}" class="h-8 w-auto">
                    @endif
                    {{ $settings['site_name'] ?? config('app.name') }}
                </a>

                @if($navigation->count())
                    <nav class="hidden flex-wrap items-center gap-6 text-sm font-semibold text-slate-600 md:flex" aria-label="Primary">
                        @foreach($navigation as $item)
                            <a href="{{ $item->url }}" class="rounded hover:text-navy-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-600">{{ $item->label }}</a>
                        @endforeach
                    </nav>
                @endif

                <a href="{{ route('login') }}" class="hidden shrink-0 rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 md:inline-flex focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-600">
                    Superadmin login
                </a>

                @if($navigation->count())
                    <details class="relative md:hidden">
                        <summary class="flex h-9 w-9 cursor-pointer list-none items-center justify-center rounded-md border border-slate-300 text-slate-600 [&::-webkit-details-marker]:hidden" aria-label="Open menu">
                            <span aria-hidden="true">&#9776;</span>
                        </summary>
                        <nav class="absolute right-0 z-20 mt-2 w-56 rounded-md border border-slate-200 bg-white p-2 shadow-soft-lg" aria-label="Mobile">
                            @foreach($navigation as $item)
                                <a href="{{ $item->url }}" class="block rounded px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">{{ $item->label }}</a>
                            @endforeach
                            <a href="{{ route('login') }}" class="mt-1 block rounded border-t border-slate-100 px-3 py-2 pt-3 text-sm font-medium text-slate-700 hover:bg-slate-50">Superadmin login</a>
                        </nav>
                    </details>
                @endif
            </div>
        </header>

        <main>
            @foreach($page->sections as $section)
                @includeFirst(["website.sections.{$section->type}", 'website.sections.rich_text'], ['content' => $section->content])
            @endforeach
        </main>

        <footer class="border-t border-slate-200 bg-slate-50">
            <div class="mx-auto max-w-6xl px-6 py-10">
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
                <div class="mt-8 flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 pt-6 text-xs text-slate-500">
                    <span>&copy; {{ date('Y') }} {{ $settings['site_name'] ?? config('app.name') }}. All rights reserved.</span>
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
