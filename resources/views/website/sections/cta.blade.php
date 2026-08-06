<section class="border-t border-slate-200 bg-slate-950">
    <div class="mx-auto max-w-4xl px-6 py-16 text-center">
        @if(!empty($content['heading']))
            <h2 class="text-3xl font-bold text-white">{{ $content['heading'] }}</h2>
        @endif
        @if(!empty($content['button_label']) && !empty($content['button_url']))
            <a href="{{ $content['button_url'] }}" class="mt-8 inline-block rounded-md bg-white px-6 py-3 text-sm font-bold text-slate-950 shadow-sm hover:bg-slate-100">{{ $content['button_label'] }}</a>
        @endif
    </div>
</section>
