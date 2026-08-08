<section class="mx-auto max-w-5xl px-6 py-20 text-center sm:py-28">
    @if(!empty($content['heading']))
        <h1 class="text-4xl font-bold tracking-tight text-navy-900 sm:text-5xl">{{ $content['heading'] }}</h1>
    @endif
    @if(!empty($content['subheading']))
        <p class="mx-auto mt-5 max-w-2xl text-lg text-slate-600">{{ $content['subheading'] }}</p>
    @endif
    @if(!empty($content['image_url']))
        <img src="{{ $content['image_url'] }}" alt="" loading="lazy" class="mx-auto mt-10 max-h-96 rounded-lg border border-slate-200 shadow-soft-lg">
    @endif
    @if(!empty($content['button_label']) && !empty($content['button_url']))
        <a href="{{ $content['button_url'] }}" class="mt-8 inline-block rounded-md bg-brand-600 px-6 py-3 text-sm font-bold text-white shadow-soft transition hover:bg-brand-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-600 focus-visible:ring-offset-2 motion-reduce:transition-none">{{ $content['button_label'] }}</a>
    @endif
</section>
