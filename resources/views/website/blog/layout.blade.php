@php
    $siteName = $settings['site_name'] ?? config('app.name');
    $headerNavigation = $navigation->where('menu_group', 'header')->whereNull('parent_id');
    $footerNavigation = $navigation->where('menu_group', 'footer')->whereNull('parent_id');
    $configuredLogo = $settings['logo_url'] ?? null;
    $configuredDarkLogo = $settings['logo_dark_url'] ?? null;
    $lightLogo = $configuredLogo ?: (file_exists(public_path('branding/light_logo.png')) ? asset('branding/light_logo.png') : (file_exists(public_path('branding/logo/light_logo.png')) ? asset('branding/logo/light_logo.png') : null));
    $darkLogo = $configuredDarkLogo ?: $configuredLogo ?: (file_exists(public_path('branding/dark_logo.png')) ? asset('branding/dark_logo.png') : (file_exists(public_path('branding/logo/dark_logo.png')) ? asset('branding/logo/dark_logo.png') : $lightLogo));
@endphp
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>@yield('title') · {{ $siteName }}</title>
    @yield('meta')
    @if($settings['favicon_url'] ?? null)<link rel="icon" href="{{ $settings['favicon_url'] }}">@endif
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700|manrope:500,600,700&display=swap" rel="stylesheet">
    @vite('resources/css/app.css')
    <style>
        :root { --cms-accent:{{ $settings['accent_color'] ?? '#059669' }}; --cms-container:{{ $settings['container_width'] ?? '1280px' }}; }
        body { font-family:'{{ $settings['body_font'] ?? 'Inter' }}',ui-sans-serif,system-ui,sans-serif; }
        h1,h2,h3 { font-family:'{{ $settings['heading_font'] ?? 'Manrope' }}',ui-sans-serif,system-ui,sans-serif; }
        .cms-container { max-width:var(--cms-container); }
    </style>
</head>
<body class="min-h-screen overflow-x-hidden bg-white text-slate-700 antialiased">
    <header class="sticky top-0 z-40 border-b border-slate-200/80 bg-white/90 backdrop-blur-xl">
        <div class="cms-container mx-auto flex min-h-[72px] items-center justify-between gap-5 px-6 py-3">
            <a href="/" class="font-bold text-slate-950">@if($lightLogo)<img src="{{ $lightLogo }}" alt="{{ $siteName }}" class="h-8 w-auto max-w-[170px]">@else{{ $siteName }}@endif</a>
            <nav class="hidden items-center gap-7 text-sm font-semibold text-slate-600 lg:flex" aria-label="Primary">@foreach($headerNavigation as $item)<a href="{{ $item->url }}" class="hover:text-slate-950">{{ $item->label }}</a>@endforeach</nav>
            <div class="flex items-center gap-2"><a href="{{ route('portal.login') }}" class="hidden rounded-lg px-3 py-2 text-sm font-semibold text-slate-700 sm:block">Sign in</a><a href="{{ route('portal.register') }}" class="rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-emerald-500">Start free</a></div>
        </div>
    </header>
    <main>@yield('content')</main>
    @include('website.partials.footer')
</body>
</html>
