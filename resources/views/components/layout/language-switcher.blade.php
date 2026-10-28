@props(['locale', 'alternates' => [], 'id' => 'language-switcher'])
@php
    use App\Support\Locales;
    $codes = Locales::enabled();
@endphp
{{--
    The edition switcher. A disclosure — a button that shows a list of links —
    rather than an ARIA menu: these are navigation links, and a menu role would
    take away the list semantics and the ordinary Tab order (CLAUDE.md §6).

    Each language is named in itself, carries its own lang and dir, and leads
    to this page's version in that edition when one exists. When it does not,
    the link is that edition's front page and says so, in that language: the
    reader is never sent to a page in one language that claims to be another.
    Hidden entirely while only one edition is live.
--}}
@if (count($codes) > 1)
    <div x-data="{ open: false }" class="relative" @keydown.escape.window="open = false" @click.outside="open = false">
        <button type="button"
                class="inline-flex min-h-6 items-center gap-1 text-ink transition hover:text-g-700"
                aria-expanded="false" :aria-expanded="open.toString()" aria-controls="{{ $id }}"
                @click="open = ! open">
            <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                <circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 010 18M12 3a14 14 0 000 18"/>
            </svg>
            <span class="sr-only">{{ __('site.switcher.label') }}:</span>
            <span lang="{{ Locales::tag($locale) }}">{{ Locales::name($locale) }}</span>
        </button>
        <ul id="{{ $id }}" x-show="open" x-cloak style="display:none"
            class="absolute end-0 top-full z-50 mt-1 min-w-[11rem] rounded-lg border border-line bg-white py-1 text-sm shadow-lg">
            @foreach ($codes as $code)
                @php $href = $alternates[$code] ?? route('web.home', $code); @endphp
                <li>
                    <a href="{{ $href }}" hreflang="{{ Locales::tag($code) }}" lang="{{ Locales::tag($code) }}" dir="{{ Locales::dir($code) }}"
                       @if ($code === $locale) aria-current="true" @endif
                       @class(['flex min-h-6 flex-col px-3 py-1.5 transition hover:bg-cream', 'font-semibold text-g-900' => $code === $locale, 'text-ink' => $code !== $locale])>
                        <span>{{ Locales::name($code) }}</span>
                        @unless (isset($alternates[$code]))
                            <span class="text-[0.6875rem] text-ink-3">{{ __('site.switcher.front_page_only', [], $code) }}</span>
                        @endunless
                    </a>
                </li>
            @endforeach
        </ul>
    </div>
@endif
