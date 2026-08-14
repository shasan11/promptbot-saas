@extends('website.blog.layout')
@section('title', $post->seo['title'] ?? $post->title)
@section('meta')
    @if($post->seo['description'] ?? $post->excerpt)<meta name="description" content="{{ $post->seo['description'] ?? $post->excerpt }}">@endif
    <meta name="robots" content="{{ $post->robots_index ? 'index' : 'noindex' }},follow">
    <link rel="canonical" href="{{ $post->canonical_url ?: rtrim($settings['canonical_base_url'] ?? url('/'), '/').'/blog/'.$post->slug }}">
    <meta property="og:type" content="article">
    <meta property="og:title" content="{{ $post->seo['title'] ?? $post->title }}">
    @if($post->featured_image)<meta property="og:image" content="{{ $post->featured_image }}">@endif
@endsection
@section('content')
<article>
    <header class="relative overflow-hidden bg-slate-950 px-6 pb-14 pt-8 text-white sm:pb-20 sm:pt-10">
        <div class="pointer-events-none absolute inset-0 opacity-70" aria-hidden="true" style="background-image:linear-gradient(to right,rgba(16,185,129,.10) 1px,transparent 1px),linear-gradient(to bottom,rgba(16,185,129,.10) 1px,transparent 1px),linear-gradient(to right,rgba(16,185,129,.05) 1px,transparent 1px),linear-gradient(to bottom,rgba(16,185,129,.05) 1px,transparent 1px);background-size:44px 44px,44px 44px,176px 176px,176px 176px"></div>
        <div class="pointer-events-none absolute left-1/2 top-0 h-80 w-80 -translate-x-1/2 rounded-full bg-emerald-400/15 blur-3xl" aria-hidden="true"></div>
        <div class="relative mx-auto max-w-5xl text-center">
            <a href="{{ route('website.blog') }}" class="inline-flex items-center gap-2 text-xs font-bold uppercase tracking-[.14em] text-emerald-300 transition hover:text-emerald-200"><span aria-hidden="true">&larr;</span> All resources</a>
            @if($post->categories->count() || $post->tags->count())
                <div class="mt-5 flex flex-wrap justify-center gap-2">
                    @foreach($post->categories->concat($post->tags) as $taxonomy)
                        <span class="rounded-full border border-emerald-300/20 bg-emerald-300/10 px-3 py-1 text-[11px] font-semibold text-emerald-100">{{ $taxonomy->name }}</span>
                    @endforeach
                </div>
            @endif
            <h1 class="mx-auto mt-5 max-w-4xl text-3xl font-bold leading-[1.08] tracking-[-.04em] sm:text-5xl lg:text-6xl">{{ $post->title }}</h1>
            @if($post->excerpt)<p class="mx-auto mt-5 max-w-2xl text-base leading-7 text-slate-300 sm:text-lg">{{ $post->excerpt }}</p>@endif
            <div class="mt-6 flex flex-wrap items-center justify-center gap-x-3 gap-y-1 text-xs font-medium text-slate-400">
                @if($post->author)<span class="text-slate-200">By {{ $post->author->name }}</span><span aria-hidden="true">&bull;</span>@endif
                <time datetime="{{ $post->published_at?->toDateString() }}">{{ $post->published_at?->format('F j, Y') }}</time>
            </div>
        </div>
    </header>
    <div class="mx-auto max-w-5xl px-6 {{ $post->featured_image ? '-mt-10 relative sm:-mt-14' : 'pt-12 sm:pt-16' }}">
        @if($post->featured_image)
            <img src="{{ $post->featured_image }}" alt="" class="mb-12 aspect-[16/8] w-full rounded-2xl border border-white/15 object-cover shadow-2xl shadow-slate-950/20 sm:rounded-3xl">
        @endif
        <div class="mx-auto max-w-3xl pb-14 sm:pb-20">
            <div class="prose prose-lg prose-slate max-w-none prose-headings:font-bold prose-headings:tracking-tight prose-a:text-emerald-700">{!! $post->content !!}</div>
        </div>
    </div>
</article>
@endsection
