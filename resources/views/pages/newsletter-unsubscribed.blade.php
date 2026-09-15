@php $locale = app()->getLocale(); @endphp

<x-layout.public title="تم إلغاء الاشتراك" :noindex="true">
    <x-ui.container class="py-10">
        <div class="mx-auto max-w-[760px]">
            <header class="mb-8 border-b border-line pb-6">
                <h1 class="font-display text-3xl font-semibold leading-tight text-g-950 sm:text-4xl">
                    تم إلغاء الاشتراك
                </h1>
                <p class="mt-4 text-lg leading-relaxed text-ink-3">
                    لن تصلك النشرة بعد الآن. شكرًا على وقتك معنا.
                </p>
            </header>

            <p class="text-sm text-ink-3">
                غيّرت رأيك؟ يمكنك الاشتراك من جديد في أي وقت.
            </p>

            <div class="mt-8 flex flex-wrap gap-3">
                <x-ui.button :href="route('web.newsletter', $locale)">العودة إلى النشرة</x-ui.button>
                <x-ui.button variant="secondary" :href="route('web.home', $locale)">الصفحة الرئيسية</x-ui.button>
            </div>
        </div>
    </x-ui.container>
</x-layout.public>
