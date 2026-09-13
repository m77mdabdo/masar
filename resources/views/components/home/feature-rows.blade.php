@props(['section', 'href' => null])
@php
    $items = $section['items'];
    $feature = $items->first();
    $rows = $items->skip(1)->take(5);
@endphp
{{-- One photograph beside five rows. A rail with a point of view rather than
     five equal cards — and the weight difference is what says which is which. --}}
<section class="mb-0" aria-labelledby="section-{{ $section['type'] }}-heading">
    <x-ui.section-rule :title="$section['title']" :href="$href" id="section-{{ $section['type'] }}-heading" />

    <div class="grid gap-8 lg:grid-cols-[1.25fr_0.75fr]">
        <x-article.card-overlay :article="$feature" size="lg" />

        <div>
            @foreach ($rows as $article)
                <x-article.card-row :article="$article" />
            @endforeach
        </div>
    </div>
</section>
