@props(['section', 'ranked' => false, 'marker' => null, 'href' => null, 'weight' => 'rank'])
{{-- A narrow column. `weight` picks which card it runs: the success-stories
     column takes photographs, most-read and editor's picks take text only. --}}
<section aria-labelledby="rail-{{ $section['type'] }}-heading">
    <x-ui.section-rule :title="$section['title']" :href="$href" id="rail-{{ $section['type'] }}-heading" :tight="true" />

    <div>
        @foreach ($section['items']->take($weight === 'story' ? 3 : 5) as $article)
            @if ($weight === 'story')
                <x-article.card-story :article="$article" />
            @else
                <x-article.card-rank
                    :article="$article"
                    :rank="$ranked ? $loop->iteration : null"
                    :marker="$marker"
                />
            @endif
        @endforeach
    </div>
</section>
