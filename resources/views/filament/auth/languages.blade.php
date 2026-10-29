{{-- The six editions on the sign-in page. Each name is in its own language and
     script, so a reader who cannot read the current one can still find theirs. --}}
<nav aria-label="{{ __('admin.language.menu') }}" class="mt-6 flex flex-wrap justify-center gap-x-4 gap-y-2 text-sm">
    @foreach (\App\Support\Locales::codes() as $code)
        <a href="{{ request()->url() }}?lang={{ $code }}"
           hreflang="{{ \App\Support\Locales::tag($code) }}" lang="{{ \App\Support\Locales::tag($code) }}" dir="{{ \App\Support\Locales::dir($code) }}"
           @if ($code === app()->getLocale()) aria-current="true" class="inline-flex min-h-6 items-center font-semibold text-primary-600 underline" @else class="inline-flex min-h-6 items-center text-gray-600 hover:underline dark:text-gray-300" @endif>
            {{ \App\Support\Locales::name($code) }}
        </a>
    @endforeach
</nav>
