@props(['blocks', 'article' => null])
@foreach ($blocks as $block)
    @php $data = $block->data ?? []; @endphp

    @switch ($block->type)
        @case ('paragraph')
            <p>{{ $data['text'] ?? '' }}</p>
            @break

        @case ('heading')
            @php $level = min(4, max(2, (int) ($data['level'] ?? 2))); @endphp
            <h{{ $level }} id="block-{{ $block->id }}">{{ $data['text'] ?? '' }}</h{{ $level }}>
            @break

        @case ('image')
            @php
                // A block may name a media record or a bare path. The record is
                // preferred: it is what carries the conversions, so a srcset
                // exists instead of one full-size file for every viewport.
                $blockMedia = isset($data['media_id'])
                    ? $article?->getMedia('inline')->firstWhere('id', (int) $data['media_id'])
                    : null;
            @endphp
            <figure class="not-prose my-8">
                <x-ui.responsive-image
                    :media="$blockMedia"
                    :src="$data['path'] ?? null"
                    :alt="$blockMedia?->getCustomProperty('alt') ?? ($data['alt'] ?? '')"
                    ratio="inline"
                    sizes="(max-width: 768px) 100vw, 720px"
                    class="rounded-xl"
                />
                @php $blockCredit = $blockMedia?->getCustomProperty('credit') ?? ($data['credit'] ?? null); @endphp
                @if (filled($data['caption'] ?? null) || filled($blockCredit))
                    <figcaption class="mt-2 text-sm text-ink-3">
                        {{ $data['caption'] ?? '' }}
                        @if (filled($blockCredit))<span class="text-ink-3">{{ filled($data['caption'] ?? null) ? ' — ' : '' }}{{ $blockCredit }}</span>@endif
                    </figcaption>
                @endif
            </figure>
            @break

        @case ('quote')
        @case ('pullquote')
            <x-article.pull-quote
                :text="$data['text'] ?? ''"
                :attribution="$data['attribution'] ?? null"
                :role="$data['role'] ?? null"
            />
            @break

        @case ('numbers')
            <x-article.key-numbers :numbers="$data['items'] ?? []" />
            @break

        @case ('callout')
            <aside class="not-prose my-8 rounded-xl border-s-4 border-s-mint bg-white p-5">
                <p class="text-base leading-relaxed">{{ $data['text'] ?? '' }}</p>
            </aside>
            @break

        @case ('opportunity')
            <x-article.opportunity-block
                :text="$data['text'] ?? ''"
                :ctaLabel="$data['cta_label'] ?? null"
                :ctaUrl="$data['cta_url'] ?? null"
            />
            @break

        @case ('explainer')
            <aside class="not-prose my-8 rounded-xl border border-line bg-white p-5">
                <h3 class="mb-1 font-display text-base font-semibold text-g-900">{{ $data['term'] ?? '' }}</h3>
                <p class="text-sm leading-relaxed text-ink-3">{{ $data['text'] ?? '' }}</p>
            </aside>
            @break

        @case ('table')
            @php
                $rows = array_values(array_filter(array_map('str_getcsv', preg_split('/\r\n|\r|\n/', (string) ($data['csv'] ?? '')))));
                $head = array_shift($rows) ?: [];
            @endphp
            <x-data.table :headers="$head" :caption="$data['caption'] ?? null">
                @foreach ($rows as $row)
                    <tr>
                        @foreach ($row as $cell)
                            <td class="px-4 py-3">{{ $cell }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </x-data.table>
            @break

        @case ('video')
            @if ($data['self_hosted'] ?? false)
                @php $clip = $article?->getFirstMedia('video'); @endphp
                @if ($clip)
                    <figure class="not-prose my-8">
                        <x-ui.video-player
                            :media="$clip"
                            :poster="$article?->getFirstMedia('video_poster')"
                            :title="$data['title'] ?? $article?->title"
                            ratio="inline"
                        />
                        @if (filled($data['caption'] ?? null))
                            <figcaption class="mt-2 text-sm text-ink-3">{{ $data['caption'] }}</figcaption>
                        @endif
                    </figure>
                @endif
                @break
            @endif

        @case ('embed')
            <figure class="not-prose my-8">
                {{-- No third-party script: a link out beats shipping an embed's
                     JavaScript onto every article that mentions a video. --}}
                <a href="{{ $data['url'] ?? '#' }}" rel="noopener" target="_blank"
                   class="flex items-center gap-3 rounded-xl border border-line bg-white p-4 transition hover:border-g-600">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-g-900 text-cream">▶</span>
                    <span class="min-w-0">
                        <span class="block truncate font-medium">{{ $data['title'] ?? ($data['caption'] ?? 'مشاهدة') }}</span>
                        <span class="ltr-isolate block truncate text-xs text-ink-3">{{ $data['url'] ?? '' }}</span>
                    </span>
                </a>
            </figure>
            @break

        @case ('divider')
            <x-ui.divider class="{{ ($data['spacious'] ?? false) ? 'my-12' : 'my-8' }}" />
            @break
    @endswitch
@endforeach
