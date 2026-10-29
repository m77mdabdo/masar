@php
    use App\Support\SiteCookies;
    $locale = app()->getLocale();
    $p = "pages.legal.{$page}";
    $contact = '<a href="'.e(route('web.contact', $locale)).'" class="text-gold-ink underline">'.e(__('pages.legal.contact_link')).'</a>';
    // The date these pages last changed in substance. Bump it with them.
    $updated = '2026-10-29';
    $sections = [
        'privacy' => ['reading', 'newsletter', 'server', 'never', 'services', 'rights'],
        'terms' => array_values(array_filter(['content', 'advice', 'figures', app(\App\Support\DemoMode::class)->isOn() ? 'demo' : null, 'translation', 'links', 'changes'])),
        'cookies' => ['control'],
    ][$page];
@endphp
{{--
    Privacy, terms and cookies. They describe what the code does and nothing
    more (CLAUDE.md §5): no certification, no retention period nobody set, no
    legal entity nobody named. The cookie table is App\Support\SiteCookies,
    which a test compares with the cookies a real response sets.
--}}
<x-layout.public :title="__($p.'.title')" :description="__($p.'.description')">
    <x-ui.container class="py-10">
        <div class="mx-auto max-w-[760px]">
            <header class="mb-10 border-b border-line pb-6">
                <h1 class="font-display text-3xl font-semibold leading-tight text-g-950 sm:text-4xl">{{ __($p.'.title') }}</h1>
                <p class="mt-3 text-sm text-ink-3">{{ __('pages.legal.updated', ['date' => '']) }}<time datetime="{{ $updated }}" class="ltr-isolate nums-tabular">{{ $updated }}</time></p>
                <p class="mt-4 text-lg leading-relaxed text-ink-3">
                    {{ $page === 'cookies' ? __($p.'.intro', ['count' => count($cookies)]) : __($p.'.intro') }}
                </p>
            </header>

            <div class="prose-masar">
                @if ($page === 'cookies')
                    {{-- A table on wide screens; on a phone the same cells stack, one
                         cookie per block, so no column is lost to a sideways scroll. --}}
                    <div class="not-prose overflow-hidden rounded-xl border border-line bg-white">
                        <table class="w-full text-start text-sm">
                            <thead class="hidden bg-cream text-xs text-ink-3 sm:table-header-group">
                                <tr>
                                    <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('pages.legal.cookies.col_name') }}</th>
                                    <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('pages.legal.cookies.col_purpose') }}</th>
                                    <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('pages.legal.cookies.col_duration') }}</th>
                                    <th scope="col" class="px-4 py-3 text-start font-semibold">{{ __('pages.legal.cookies.col_kind') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($cookies as $cookie)
                                    <tr class="block border-t border-line first:border-t-0 sm:table-row">
                                        <td class="block px-4 pt-3 sm:table-cell sm:py-3"><code class="ltr-isolate text-[0.8rem] text-g-900">{{ $cookie['name'] }}</code></td>
                                        <td class="block px-4 pt-1 text-ink sm:table-cell sm:py-3">{{ __('pages.legal.cookies.'.$cookie['purpose']) }}</td>
                                        <td class="block px-4 pt-1 text-ink-3 sm:table-cell sm:py-3 sm:whitespace-nowrap">{{ SiteCookies::duration($cookie['minutes']) }}</td>
                                        <td class="block px-4 pb-3 pt-1 text-ink-3 sm:table-cell sm:py-3">{{ __('pages.legal.cookies.'.$cookie['kind']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                @foreach ($sections as $section)
                    <h2>{{ __($p.'.'.$section) }}</h2>
                    <p>
                        @if (str_contains((string) __($p.'.'.$section.'_body', [], 'ar'), ':link'))
                            {{-- The link is built and escaped here; the sentence is ours. --}}
                            {!! __($p.'.'.$section.'_body', ['link' => $contact]) !!}
                        @else
                            {{ __($p.'.'.$section.'_body', ['hours' => (int) config('masar.views.dedupe_hours', 24)]) }}
                        @endif
                    </p>
                @endforeach
            </div>
        </div>
    </x-ui.container>
</x-layout.public>
