<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Blog · {{ $settings['site_name'] ?? config('app.name') }}</title>
    @if($settings['default_description'] ?? null)<meta name="description" content="{{ $settings['default_description'] }}">@endif
    <link rel="canonical" href="{{ rtrim($settings['canonical_base_url'] ?? url('/'), '/').'/blog' }}">
    <meta name="robots" content="index,follow">
    @if($settings['favicon_url'] ?? null)<link rel="icon" href="{{ $settings['favicon_url'] }}">@endif
    @vite('resources/css/app.css')
</head>
<body class="bg-slate-50 text-slate-800">
<header class="border-b bg-white"><div class="mx-auto flex max-w-5xl items-center justify-between px-6 py-4"><a href="/" class="font-bold text-slate-950">{{ $settings['site_name'] ?? config('app.name') }}</a><div class="flex gap-3 text-sm font-semibold"><a href="{{ route('portal.login') }}">Customer login</a><a href="{{ route('portal.register') }}" class="rounded-lg bg-slate-950 px-3 py-2 text-white">Start free trial</a></div></div></header>
<main class="mx-auto max-w-5xl px-6 py-16"><h1 class="text-4xl font-bold">Latest articles</h1><div class="mt-10 grid gap-6 md:grid-cols-2">@forelse($posts as $post)<article class="rounded-2xl border bg-white p-6">@if($post->featured_image)<img src="{{ $post->featured_image }}" alt="" class="mb-5 aspect-video w-full rounded-xl object-cover">@endif<p class="text-xs text-slate-500">{{ $post->published_at?->format('M j, Y') }}</p><h2 class="mt-2 text-xl font-bold"><a href="{{ route('website.blog.show', $post->slug) }}">{{ $post->title }}</a></h2><p class="mt-3 text-sm text-slate-600">{{ $post->excerpt }}</p></article>@empty<p>No articles have been published yet.</p>@endforelse</div><div class="mt-8">{{ $posts->links() }}</div></main>
</body></html>
