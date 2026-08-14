@php
    $pricingId = 'pricing-'.$section->id;
    $isManual = ($content['data_source'] ?? 'live_plans') === 'manual';
    $plans = $isManual ? collect($content['items'] ?? []) : $publicPlans;
@endphp
<section id="{{ $pricingId }}" class="bg-white py-14 sm:py-20"><div class="cms-container mx-auto px-6">
    <div class="mx-auto max-w-3xl text-center"><p class="cms-eyebrow">Simple pricing</p><h2 class="cms-heading mt-4">{{ $content['heading'] ?? 'Pricing' }}</h2>@if($content['description'] ?? null)<p class="cms-copy mx-auto mt-4">{{ $content['description'] }}</p>@endif</div>
    @if($content['show_billing_toggle'] ?? true)<div class="mt-8 flex justify-center"><div class="rounded-xl border border-slate-200 bg-slate-50 p-1 text-sm shadow-sm"><button type="button" data-pricing-interval="monthly" class="rounded-lg px-4 py-2 font-semibold transition">Monthly</button><button type="button" data-pricing-interval="yearly" class="rounded-lg px-4 py-2 font-semibold transition">Yearly</button></div></div>@endif
    <div class="mt-10 grid gap-5 md:grid-cols-3">@foreach($plans as $plan)
        @php
            $slug = $isManual ? str($plan['name'] ?? 'plan')->slug() : $plan->slug;
            $name = $isManual ? ($plan['name'] ?? '') : $plan->name;
            $description = $isManual ? ($plan['description'] ?? '') : $plan->description;
            $currency = $isManual ? ($plan['currency'] ?? 'USD') : $plan->currency;
            $monthly = $isManual ? ($plan['monthly_price'] ?? 0) : $plan->monthly_price;
            $annual = $isManual ? ($plan['annual_price'] ?? 0) : $plan->annual_price;
            $url = $isManual ? ($plan['url'] ?? '/account/register') : route('portal.register', ['plan' => $plan->slug, 'interval' => 'monthly']);
        @endphp
        <article class="relative rounded-3xl border {{ ($content['highlighted_plan'] ?? '') === $slug ? 'border-emerald-500 ring-4 ring-emerald-50' : 'border-slate-200' }} bg-white p-7 shadow-lg shadow-slate-950/5"><h3 class="text-xl font-bold text-slate-950">{{ $name }}</h3><p class="mt-3 min-h-12 text-sm leading-6 text-slate-500">{{ $description }}</p><p class="mt-6 text-4xl font-bold tracking-tight text-slate-950"><span data-price-monthly>{{ $currency }} {{ number_format((float) $monthly, 2) }}<small class="ml-1 text-sm font-normal text-slate-500">/month</small></span><span data-price-annual class="hidden">{{ $currency }} {{ number_format((float) $annual, 2) }}<small class="ml-1 text-sm font-normal text-slate-500">/year</small></span></p><a href="{{ $url }}" data-signup-url="{{ $url }}" class="cms-button mt-7 block px-4 py-3.5 text-center text-sm font-bold">{{ $content['cta_label'] ?? 'Start free trial' }}</a></article>
    @endforeach</div>
</div></section>
@if($content['show_billing_toggle'] ?? true)<script>document.addEventListener('DOMContentLoaded',()=>{const root=document.getElementById(@json($pricingId));if(!root)return;const select=(interval)=>{root.querySelectorAll('[data-price-monthly]').forEach(el=>el.classList.toggle('hidden',interval!=='monthly'));root.querySelectorAll('[data-price-annual]').forEach(el=>el.classList.toggle('hidden',interval!=='yearly'));root.querySelectorAll('[data-pricing-interval]').forEach(el=>el.classList.toggle('bg-emerald-600',el.dataset.pricingInterval===interval));root.querySelectorAll('[data-pricing-interval]').forEach(el=>el.classList.toggle('text-white',el.dataset.pricingInterval===interval));root.querySelectorAll('[data-signup-url]').forEach(el=>{const url=new URL(el.dataset.signupUrl,window.location.origin);url.searchParams.set('interval',interval);el.href=url.pathname+url.search})};root.querySelectorAll('[data-pricing-interval]').forEach(el=>el.addEventListener('click',()=>select(el.dataset.pricingInterval)));select(@json(($content['default_interval'] ?? 'monthly') === 'annual' ? 'yearly' : ($content['default_interval'] ?? 'monthly')))});</script>@endif
