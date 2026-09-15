@php $locale = app()->getLocale(); @endphp

{{-- The GET that does nothing. Mail clients and scanners fetch every link in a
     message before a human sees it, so the act of leaving is the POST below. --}}
<x-layout.public title="إلغاء الاشتراك" :noindex="true">
    <x-ui.container class="py-10">
        <div class="mx-auto max-w-[760px]">
            <header class="mb-8 border-b border-line pb-6">
                <h1 class="font-display text-3xl font-semibold leading-tight text-g-950 sm:text-4xl">
                    إلغاء الاشتراك في النشرة
                </h1>
                <p class="mt-4 text-lg leading-relaxed text-ink-3">
                    تأكيد أخير قبل أن نوقف الرسائل عن
                    <bdi class="font-medium text-g-950">{{ $subscriber->email }}</bdi>.
                </p>
            </header>

            <form method="POST" action="{{ url()->full() }}">
                @csrf
                <x-ui.button type="submit" size="lg">نعم، ألغِ اشتراكي</x-ui.button>
                <a href="{{ route('web.home', $locale) }}" class="ms-3 text-sm text-ink-3 underline hover:text-g-900">
                    تراجع
                </a>
            </form>
        </div>
    </x-ui.container>
</x-layout.public>
