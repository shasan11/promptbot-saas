@php
    $faqSchema = [
        '@'.'context' => 'https://schema.org',
        '@'.'type' => 'FAQPage',
        'mainEntity' => collect($content['items'] ?? [])->map(fn ($item) => [
            '@'.'type' => 'Question', 'name' => $item['question'] ?? '',
            'acceptedAnswer' => ['@'.'type' => 'Answer', 'text' => $item['answer'] ?? ''],
        ])->all(),
    ];
@endphp
<section class="bg-slate-50 py-14 sm:py-20"><div class="mx-auto max-w-4xl px-6"><div class="text-center"><h2 class="cms-heading">{{ $content['heading'] ?? 'Frequently asked questions' }}</h2>@if(!empty($content['description']))<p class="cms-copy mx-auto mt-4">{{ $content['description'] }}</p>@endif</div><div class="mt-12 space-y-3">@foreach($content['items'] ?? [] as $item)<details class="group rounded-xl border border-slate-200 bg-white p-5 shadow-sm"><summary class="flex cursor-pointer list-none items-center justify-between gap-4 font-bold text-slate-950 [&::-webkit-details-marker]:hidden">{{ $item['question'] ?? '' }}<span class="text-xl font-light text-emerald-600 transition group-open:rotate-45" aria-hidden="true">+</span></summary><p class="mt-4 max-w-3xl text-sm leading-7 text-slate-600">{{ $item['answer'] ?? '' }}</p></details>@endforeach</div></div>@if(!empty($content['items']))<script type="application/ld+json">{!! json_encode($faqSchema, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) !!}</script>@endif</section>
