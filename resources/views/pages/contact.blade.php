<x-layout.public title="اتصل بنا" description="تواصل مع فرق مسار.">
    <x-ui.container class="py-10">
        <div class="mx-auto max-w-[760px]">
            <header class="mb-10 border-b border-line pb-6">
                <h1 class="font-display text-3xl font-semibold text-g-950 sm:text-4xl">اتصل بنا</h1>
                <p class="mt-3 text-ink-3">اختر الفريق المناسب ليصلك رد أسرع.</p>
            </header>

            <div class="grid gap-4 sm:grid-cols-2">
                @foreach ([
                    ['key' => 'contact.editorial_email', 'title' => 'التحرير', 'note' => 'أخبار، تصحيحات، اقتراحات تغطية.'],
                    ['key' => 'contact.advertising_email', 'title' => 'الإعلانات', 'note' => 'الرعاية والمحتوى المدفوع.'],
                    ['key' => 'contact.careers_email', 'title' => 'التوظيف', 'note' => 'الانضمام إلى الفريق.'],
                    ['key' => 'contact.general_email', 'title' => 'عام', 'note' => 'كل ما عدا ذلك.'],
                ] as $desk)
                    @php $email = setting($desk['key']); @endphp
                    <div class="rounded-xl border border-line bg-white p-5">
                        <h2 class="font-display text-lg font-semibold text-g-900">{{ $desk['title'] }}</h2>
                        <p class="mt-1 text-sm text-ink-3">{{ $desk['note'] }}</p>
                        @if ($email)
                            <a href="mailto:{{ $email }}" class="ltr-isolate mt-3 inline-block text-sm text-g-700 underline underline-offset-4">
                                {{ $email }}
                            </a>
                        @else
                            <p class="mt-3 text-sm text-ink-3">لم يُضف بريد لهذا الفريق بعد.</p>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    </x-ui.container>
</x-layout.public>
