@props(['article', 'exclude' => null])
@php
    use App\Enums\ArticleSection;

    // Two kinds of block never render in the stream. `exclude` is the one the
    // hero borrowed — dropped rather than duplicated and hidden, so the quote
    // exists once in the document. `expert` is a band the page places itself,
    // between the key numbers and the related companies.
    $blocks = $article->blocks
        ->reject(fn ($block): bool => $exclude && $block->id === $exclude->id)
        ->reject(fn ($block): bool => $block->type === 'expert');

    $sectioned = $blocks->filter(fn ($block): bool => $block->section !== null);
    $loose = $blocks->filter(fn ($block): bool => $block->section === null);
@endphp

@if ($sectioned->isNotEmpty())
    {{--
        The four questions are the article. Each carries its own blocks — the
        inline figure inside "what happened", the quote box inside "why it
        matters" — rather than a band of summaries sitting above a second copy
        of the same material.
    --}}
    @foreach (ArticleSection::cases() as $section)
        @php $own = $sectioned->where('section', $section); @endphp
        @continue ($own->isEmpty())
        <x-article.section
            :section="$section"
            :blocks="$own"
            :article="$article"
            :lead="$article->{$section->field()}"
        />
    @endforeach

    {{-- Anything the editor left unassigned still renders, after the four.
         A block with no section is not a block nobody should read. --}}
    @if ($loose->isNotEmpty())
        <div class="prose-masar">
            <x-article.blocks :blocks="$loose" :article="$article" />
        </div>
    @endif
@else
    {{-- No sections: an opinion piece or a success story. Forcing four
         headings over a paragraph each would make the shape a lie.

         The four answers still have to appear — `why_it_matters` is a publish
         gate rule — so they follow the piece as a band rather than framing it. --}}
    <div class="prose-masar">
        <x-article.blocks :blocks="$blocks" :article="$article" />
    </div>

    <x-article.path-steps :article="$article" />
@endif
