<section class="border-t border-slate-200 bg-navy-900">
    <div class="mx-auto max-w-4xl px-6 py-16 text-center sm:py-20">
        @if(!empty($content['heading']))
            <h2 class="text-3xl font-bold text-white">{{ $content['heading'] }}</h2>
        @endif
        @if(!empty($content['description']))<p class="mx-auto mt-3 max-w-2xl text-slate-300">{{ $content['description'] }}</p>@endif
        <div class="mt-8 flex flex-wrap justify-center gap-3">
            @if(!empty($content['primary_label']) && !empty($content['primary_url']))<a href="{{ $content['primary_url'] }}" class="rounded-md bg-brand-500 px-6 py-3 text-sm font-bold text-white">{{ $content['primary_label'] }}</a>@endif
            @if(!empty($content['secondary_label']) && !empty($content['secondary_url']))<a href="{{ $content['secondary_url'] }}" class="rounded-md border border-slate-600 px-6 py-3 text-sm font-bold text-white">{{ $content['secondary_label'] }}</a>@endif
        </div>
    </div>
</section>
