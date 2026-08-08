<section class="border-t border-slate-200 bg-navy-900">
    <div class="mx-auto max-w-4xl px-6 py-16 text-center sm:py-20">
        @if(!empty($content['heading']))
            <h2 class="text-3xl font-bold text-white">{{ $content['heading'] }}</h2>
        @endif
        @if(!empty($content['button_label']) && !empty($content['button_url']))
            <a href="{{ $content['button_url'] }}" class="mt-8 inline-block rounded-md bg-brand-500 px-6 py-3 text-sm font-bold text-white shadow-soft transition hover:bg-brand-400 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-navy-900 motion-reduce:transition-none">{{ $content['button_label'] }}</a>
        @endif
    </div>
</section>
