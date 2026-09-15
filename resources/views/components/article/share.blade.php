@props(['article', 'url', 'variant' => 'stack', 'tone' => 'light'])
{{-- contrast-safe: mint only under tone=dark, which the hero scrim backs. Mint is 2.07:1 on cream and 7.63:1 on g-950. --}}
@php
    $text = rawurlencode($article->title);
    $encoded = rawurlencode($url);

    // Plain links, no third-party SDKs: a share button should not cost the
    // reader a tracking script on every article.
    $targets = [
        ['name' => 'LinkedIn', 'href' => "https://www.linkedin.com/sharing/share-offsite/?url={$encoded}", 'path' => 'M4.98 3.5a2 2 0 11-.02 4 2 2 0 01.02-4zM3 8.98h4V21H3zM9 8.98h3.8v1.64h.05A4.17 4.17 0 0116.6 8.7c4 0 4.4 2.5 4.4 5.76V21h-4v-5.6c0-1.34-.03-3.06-1.9-3.06s-2.2 1.46-2.2 2.96V21H9z'],
        ['name' => 'X', 'href' => "https://x.com/intent/post?url={$encoded}&text={$text}", 'path' => 'M17.53 3h3.02l-6.6 7.55L21.75 21h-5.9l-4.62-6.05L5.94 21H2.92l7.06-8.07L2.5 3h6.05l4.18 5.53zm-1.06 16.2h1.67L7.6 4.71H5.8z'],
        ['name' => 'WhatsApp', 'href' => "https://wa.me/?text={$text}%20{$encoded}", 'path' => 'M12 2a10 10 0 00-8.6 15.03L2 22l5.1-1.33A10 10 0 1012 2zm0 2a8 8 0 11-4.2 14.8l-.3-.18-2.6.68.7-2.53-.2-.32A8 8 0 0112 4zm4.3 10.1c-.24-.12-1.42-.7-1.64-.78s-.38-.12-.54.12-.62.78-.76.94-.28.18-.52.06a6.5 6.5 0 01-1.92-1.18 7.2 7.2 0 01-1.33-1.65c-.14-.24 0-.37.1-.49l.36-.42c.12-.14.16-.24.24-.4s.04-.3-.02-.42-.54-1.3-.74-1.78-.4-.4-.54-.4h-.46a.9.9 0 00-.64.3 2.68 2.68 0 00-.84 2 4.66 4.66 0 00.98 2.47 10.65 10.65 0 004.08 3.6c.57.25 1.01.4 1.36.5a3.26 3.26 0 001.5.1 2.45 2.45 0 001.6-1.14 2 2 0 00.14-1.13c-.06-.1-.22-.16-.46-.28z'],
        ['name' => 'بريد', 'href' => "mailto:?subject={$text}&body={$encoded}", 'path' => 'M20 4H4a2 2 0 00-2 2v12a2 2 0 002 2h16a2 2 0 002-2V6a2 2 0 00-2-2zm0 4.24l-8 5-8-5V6.4l8 5 8-5z'],
    ];
@endphp

@if ($variant === 'icons')
    <ul class="flex items-center gap-1" aria-label="مشاركة المادة">
        @foreach ($targets as $target)
            <li>
                <a href="{{ $target['href'] }}"
                   @if (! str_starts_with($target['href'], 'mailto:')) target="_blank" rel="noopener" @endif
                   @class([
                       'flex h-8 w-8 items-center justify-center rounded-full border transition',
                       'border-cream/35 text-cream/85 hover:border-mint hover:text-mint' => $tone === 'dark',
                       'border-line text-ink-3 hover:border-g-600 hover:text-g-700' => $tone !== 'dark',
                   ])
                   title="{{ $target['name'] }}">
                    <span class="sr-only">مشاركة عبر {{ $target['name'] }}</span>
                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                        <path d="{{ $target['path'] }}" />
                    </svg>
                </a>
            </li>
        @endforeach
    </ul>
@else
    <div {{ $attributes }}>
        <h2 class="mb-3 font-display text-xs font-semibold uppercase tracking-wide text-ink-3">مشاركة</h2>
        <div class="flex flex-wrap gap-2">
            @foreach ($targets as $target)
                <a href="{{ $target['href'] }}"
                   @if (! str_starts_with($target['href'], 'mailto:')) target="_blank" rel="noopener" @endif
                   class="rounded-lg border border-line px-3 py-1.5 text-xs transition hover:border-g-600">
                    {{ $target['name'] }}
                </a>
            @endforeach
        </div>
    </div>
@endif
