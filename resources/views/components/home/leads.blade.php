@props(['section'])
{{-- Three cells on a line-coloured ground, so the 1px gaps read as rules rather
     than as margins. Bottom border closes the strip against the section below. --}}
<section class="mb-0 border-b border-line" aria-labelledby="leads-heading">
    <h2 id="leads-heading" class="sr-only">{{ $section['title'] }}</h2>
    <div class="grid gap-px bg-line sm:grid-cols-3">
        @foreach ($section['items']->take(3) as $article)
            <x-article.card-lead :article="$article" />
        @endforeach
    </div>
</section>
