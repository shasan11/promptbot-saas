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
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
        @vite('resources/css/app.css')
    </head>
    <body class="min-h-screen bg-white font-sans text-slate-700 antialiased" style="--brand: {{ $settings['primary_color'] ?? '#0f172a' }}">
        <header class="border-b border-slate-200">
            <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-4 px-6 py-4">
                <a href="{{ url('/') }}" class="flex items-center gap-2 text-lg font-bold text-slate-950">
                    @if($settings['logo_url'] ?? null)
                        <img src="{{ $settings['logo_url'] }}" alt="{{ $settings['site_name'] ?? '' }}" class="h-8 w-auto">
                    @endif
                    {{ $settings['site_name'] ?? config('app.name') }}
                </a>
                @if($navigation->count())
                    <nav class="flex flex-wrap items-center gap-6 text-sm font-semibold text-slate-600">
                        @foreach($navigation as $item)
                            <a href="{{ $item->url }}" class="hover:text-slate-950">{{ $item->label }}</a>
                        @endforeach
                    </nav>
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
                                        <li><a href="{{ $link->url }}" class="text-slate-600 hover:text-slate-950">{{ $link->label }}</a></li>
                                    @endforeach
                                </ul>
                            </div>
                        @endforeach
                    </div>
                @endif
                <div class="mt-8 flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 pt-6 text-xs text-slate-500">
                    <span>&copy; {{ date('Y') }} {{ $settings['site_name'] ?? config('app.name') }}. All rights reserved.</span>
                    <div class="flex gap-4">
                        @if($settings['contact_email'] ?? null)<a href="mailto:{{ $settings['contact_email'] }}" class="hover:text-slate-950">{{ $settings['contact_email'] }}</a>@endif
                        @if($settings['social_twitter'] ?? null)<a href="{{ $settings['social_twitter'] }}" class="hover:text-slate-950">Twitter/X</a>@endif
                        @if($settings['social_linkedin'] ?? null)<a href="{{ $settings['social_linkedin'] }}" class="hover:text-slate-950">LinkedIn</a>@endif
                    </div>
                </div>
            </div>
        </footer>
    </body>
</html>
