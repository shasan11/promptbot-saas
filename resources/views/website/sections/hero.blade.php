<section class="mx-auto max-w-5xl px-6 py-20 text-center">
    @if(!empty($content['heading']))
        <h1 class="text-4xl font-bold tracking-tight text-slate-950 sm:text-5xl">{{ $content['heading'] }}</h1>
    @endif
    @if(!empty($content['subheading']))
        <p class="mx-auto mt-5 max-w-2xl text-lg text-slate-600">{{ $content['subheading'] }}</p>
    @endif
    @if(!empty($content['image_url']))
        <img src="{{ $content['image_url'] }}" alt="" class="mx-auto mt-10 max-h-96 rounded-lg shadow-xl">
    @endif
    @if(!empty($content['button_label']) && !empty($content['button_url']))
        <a href="{{ $content['button_url'] }}" class="mt-8 inline-block rounded-md bg-slate-950 px-6 py-3 text-sm font-bold text-white shadow-sm hover:bg-blue-700">{{ $content['button_label'] }}</a>
    @endif
</section>
