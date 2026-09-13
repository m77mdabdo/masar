@props(['person', 'text'])
{{-- Rendered only when an editor named the person. The prototype is explicit
     about the rule: portraits must be real photographs of real people. So the
     portrait is a real file or a monogram, never a generated face, and an
     unattributed quote never reaches this band. --}}
@if ($person && filled($text))
    <figure class="not-prose grid grid-cols-[3.5rem_1fr] gap-4 rounded-xl border border-line bg-white p-5 sm:p-6">
        @if ($person->photo_path)
            <img src="{{ $person->photo_path }}" alt="" width="56" height="56" loading="lazy"
                 class="h-14 w-14 rounded-full object-cover" />
        @else
            <span class="flex h-14 w-14 items-center justify-center rounded-full bg-line font-display text-lg text-g-700" aria-hidden="true">
                {{ mb_substr($person->name ?? '؟', 0, 1) }}
            </span>
        @endif

        <div class="min-w-0">
            <blockquote class="font-display text-base italic leading-relaxed text-g-900 sm:text-lg">{{ $text }}</blockquote>
            <figcaption class="mt-3 text-sm">
                <span class="font-medium text-ink">{{ $person->name }}</span>
                @if ($person->translate('title'))
                    <span class="block text-ink-3">{{ $person->translate('title') }}</span>
                @endif
            </figcaption>
        </div>
    </figure>
@endif
