<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>{{ $post->seo['title'] ?? $post->title }} · {{ $settings['site_name'] ?? config('app.name') }}</title>
    @if($post->seo['description'] ?? $post->excerpt)<meta name="description" content="{{ $post->seo['description'] ?? $post->excerpt }}">@endif
    <meta name="robots" content="{{ $post->robots_index ? 'index' : 'noindex' }},follow">
    <link rel="canonical" href="{{ $post->canonical_url ?: rtrim($settings['canonical_base_url'] ?? url('/'), '/').'/blog/'.$post->slug }}">
    <meta property="og:type" content="article"><meta property="og:title" content="{{ $post->seo['title'] ?? $post->title }}">
    @if($post->seo['description'] ?? $post->excerpt)<meta property="og:description" content="{{ $post->seo['description'] ?? $post->excerpt }}">@endif
    @if($post->featured_image)<meta property="og:image" content="{{ $post->featured_image }}">@endif
    @if($settings['favicon_url'] ?? null)<link rel="icon" href="{{ $settings['favicon_url'] }}">@endif
    @vite('resources/css/app.css')
</head>
<body class="bg-white text-slate-800"><header class="border-b"><div class="mx-auto flex max-w-5xl items-center justify-between px-6 py-4"><a href="/" class="font-bold text-slate-950">{{ $settings['site_name'] ?? config('app.name') }}</a><a href="{{ route('website.blog') }}" class="text-sm font-semibold">Blog</a></div></header><article class="mx-auto max-w-3xl px-6 py-16"><a href="{{ route('website.blog') }}" class="text-sm font-semibold text-indigo-700">← Blog</a><h1 class="mt-8 text-4xl font-bold">{{ $post->title }}</h1><p class="mt-3 text-sm text-slate-500">{{ $post->published_at?->format('F j, Y') }}@if($post->author) · {{ $post->author->name }}@endif</p>@if($post->categories->count() || $post->tags->count())<div class="mt-4 flex flex-wrap gap-2">@foreach($post->categories->concat($post->tags) as $taxonomy)<span class="rounded-full bg-slate-100 px-3 py-1 text-xs">{{ $taxonomy->name }}</span>@endforeach</div>@endif @if($post->featured_image)<img src="{{ $post->featured_image }}" alt="" class="mt-8 rounded-2xl">@endif<div class="prose prose-slate mt-10 max-w-none">{!! $post->content !!}</div></article></body></html>
