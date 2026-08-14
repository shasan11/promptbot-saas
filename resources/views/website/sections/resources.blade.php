@php $posts = $publishedPosts->take(max(1, min(6, (int) ($content['limit'] ?? 3)))); @endphp
@if($posts->isNotEmpty())
<section class="bg-slate-50 py-14 sm:py-20">
    <div class="cms-container mx-auto px-6">
        <div class="flex flex-col justify-between gap-5 sm:flex-row sm:items-end">
            <div class="max-w-2xl">
                @if(!empty($content['eyebrow']))<p class="cms-eyebrow">{{ $content['eyebrow'] }}</p>@endif
                <h2 class="cms-heading mt-3">{{ $content['heading'] ?? 'Latest from PromptBot' }}</h2>
                @if(!empty($content['description']))<p class="cms-copy mt-4">{{ $content['description'] }}</p>@endif
            </div>
            @if(!empty($content['button_label']) && !empty($content['button_url']))<a href="{{ $content['button_url'] }}" class="cms-text-link">{{ $content['button_label'] }} <span aria-hidden="true">&rarr;</span></a>@endif
        </div>
        <div class="mt-10 grid gap-5 md:grid-cols-3">
            @foreach($posts as $post)
                <article class="group overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition hover:-translate-y-1 hover:shadow-xl">
                    @if($post->featured_image)<img src="{{ $post->featured_image }}" alt="" loading="lazy" class="aspect-[16/9] w-full object-cover">@else<div class="aspect-[16/9] bg-gradient-to-br from-emerald-100 via-white to-cyan-100 p-6"><div class="h-full rounded-xl border border-white/80 bg-white/50"></div></div>@endif
                    <div class="p-6"><p class="text-xs font-bold uppercase tracking-widest text-emerald-600">{{ optional($post->published_at)->format('M j, Y') }}</p><h3 class="mt-3 text-lg font-bold text-slate-950"><a href="{{ route('website.blog.show', $post->slug) }}">{{ $post->title }}</a></h3><p class="mt-3 text-sm leading-6 text-slate-600">{{ $post->excerpt }}</p></div>
                </article>
            @endforeach
        </div>
    </div>
</section>
@endif
