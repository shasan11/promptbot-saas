{!! '<?xml version="1.0" encoding="UTF-8"?>' !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
@foreach($pages as $page)
    <url><loc>{{ $page->slug === 'home' ? url('/') : url('/'.$page->slug) }}</loc><lastmod>{{ $page->updated_at->toAtomString() }}</lastmod></url>
@endforeach
@foreach(($posts ?? []) as $post)
    <url><loc>{{ url('/blog/'.$post->slug) }}</loc><lastmod>{{ $post->updated_at->toAtomString() }}</lastmod></url>
@endforeach
</urlset>
