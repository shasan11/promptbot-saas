{{-- Trusted admin-authored HTML: only central Superadmins with website.manage can write this content. --}}
<section class="bg-white py-12 sm:py-16">
    <div class="mx-auto max-w-4xl px-6">
        <div class="rounded-3xl border border-slate-200 bg-slate-50/70 p-7 leading-8 text-slate-600 shadow-sm sm:p-12 [&_a]:font-semibold [&_a]:text-emerald-700 [&_a]:underline [&_a]:underline-offset-4 [&_a:focus-visible]:outline-none [&_a:focus-visible]:ring-2 [&_a:focus-visible]:ring-emerald-600 [&_h1]:text-4xl [&_h1]:font-bold [&_h1]:tracking-tight [&_h1]:text-slate-950 [&_h2]:mt-10 [&_h2]:text-2xl [&_h2]:font-bold [&_h2]:tracking-tight [&_h2]:text-slate-950 [&_h2:first-child]:mt-0 [&_h3]:mt-8 [&_h3]:text-xl [&_h3]:font-bold [&_h3]:text-slate-950 [&_li]:mt-2 [&_ol]:my-5 [&_ol]:pl-6 [&_ol]:list-decimal [&_p]:mt-4 [&_ul]:my-5 [&_ul]:list-disc [&_ul]:pl-6">
            {!! $content['html'] ?? '' !!}
        </div>
    </div>
</section>
