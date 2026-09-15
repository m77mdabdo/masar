@php $locale = app()->getLocale(); @endphp

<x-layout.public title="تم تأكيد اشتراكك" :noindex="true">
    <x-ui.container class="py-10">
        <div class="mx-auto max-w-[760px]">
            <header class="mb-8 border-b border-line pb-6">
                <h1 class="font-display text-3xl font-semibold leading-tight text-g-950 sm:text-4xl">
                    تم تأكيد اشتراكك
                </h1>
                <p class="mt-4 text-lg leading-relaxed text-ink-3">
                    وصلتك النشرة الأسبوعية من الآن فصاعدًا، رسالة واحدة كل أسبوع.
                </p>
            </header>

            <p class="text-sm text-ink-3">
                البريد المسجَّل: <bdi class="nums-tabular font-medium text-g-950">{{ $subscriber->email }}</bdi>
            </p>

            <p class="mt-2 text-sm text-ink-3">
                يمكنك إلغاء الاشتراك في أي وقت من الرابط أسفل كل رسالة.
            </p>

            <div class="mt-8 flex flex-wrap gap-3">
                <x-ui.button :href="route('web.home', $locale)">إلى الصفحة الرئيسية</x-ui.button>
                <x-ui.button variant="secondary" :href="route('web.newsletter', $locale)">عن النشرة</x-ui.button>
            </div>
        </div>
    </x-ui.container>
</x-layout.public>
