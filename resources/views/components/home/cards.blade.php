@props(['section', 'cols' => 3, 'href' => null, 'rule' => true])
<section class="mb-0" aria-labelledby="section-{{ $section['type'] }}-heading">
    @if ($rule)
        <x-ui.section-rule :title="$section['title']" :href="$href" id="section-{{ $section['type'] }}-heading" />
    @else
        <h2 id="section-{{ $section['type'] }}-heading" class="sr-only">{{ $section['title'] }}</h2>
    @endif

    <div @class(['grid gap-5', 'sm:grid-cols-2 lg:grid-cols-3' => $cols === 3, 'sm:grid-cols-2 lg:grid-cols-4' => $cols === 4])>
        @foreach ($section['items']->take($cols) as $article)
            <x-article.card-article :article="$article" :compact="$cols === 4" />
        @endforeach
    </div>
</section>
