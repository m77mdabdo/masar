@php $locale = app()->getLocale(); @endphp

{{-- This page names nobody until there is somebody real to name. Listing the
     demo people here would invent a masthead of staff for a real brand, which
     is the fabrication rule in CLAUDE.md §5 applied to people rather than to
     quotes. `people.is_expert` is never seeded true, so in demo the list below
     is empty and the page says why. --}}
<x-layout.public title="فريق التحرير" description="من يكتب في مسار، وكيف يعمل التحرير.">
    <x-ui.container class="py-10">
        <div class="mx-auto max-w-[760px]">
            <header class="mb-10 border-b border-line pb-6">
                <h1 class="font-display text-3xl font-semibold leading-tight text-g-950 sm:text-4xl">فريق التحرير</h1>
                <p class="mt-4 text-lg leading-relaxed text-ink-3">
                    من يكتب، ومن يراجع، ومن يتحمّل مسؤولية ما يُنشر.
                </p>
            </header>

            @if ($people->isNotEmpty())
                <section class="mb-12">
                    <x-ui.section-rule title="الفريق" />
                    <div class="grid gap-4 sm:grid-cols-2">
                        @foreach ($people as $person)
                            <a href="{{ route('web.person.show', [$locale, $person->slug]) }}"
                               class="rounded-xl border border-line bg-white p-4 transition hover:border-g-600">
                                <div class="font-display text-base font-medium text-g-950">{{ $person->name }}</div>
                                @if ($person->translate('role'))
                                    <div class="mt-1 text-xs text-ink-3">{{ $person->translate('role') }}</div>
                                @endif
                            </a>
                        @endforeach
                    </div>
                </section>
            @else
                <div class="mb-12 rounded-xl border border-line bg-white p-6">
                    <p class="text-ink-3">
                        لا نعرض أسماء بعد. هذه نسخة تجريبية، والأشخاص الظاهرون في المحتوى التجريبي غير
                        حقيقيين — ونشر أسماء مُختلقة على أنها هيئة تحرير ادّعاءٌ لا نقبله عن أنفسنا.
                        تظهر الأسماء هنا حين يكون خلفها أشخاص حقيقيون وافقوا على ذلك.
                    </p>
                </div>
            @endif

            <div class="prose-masar">
                <h2>كيف يعمل التحرير</h2>
                <p>
                    تمرّ كل مادة بمسار واحد: بحث، ثم كتابة، ثم تدقيق للوقائع، ثم مراجعة تحريرية، ثم نشر.
                    لا تُنشر مادة دون أن تجتاز هذه المراحل، ولا يملك أحد خارج التحرير تجاوزها.
                </p>

                <h2>ما الذي يوقف النشر</h2>
                <p>
                    النظام نفسه يمنع النشر إذا نقص أي من هذه: ملخّص في ثلاثين ثانية، مصدر واحد على الأقل
                    برابط، صورة رئيسية مع نصّها البديل، اجتياز مرحلة التدقيق، وإجابة عن سؤال «لماذا يهم».
                    وإذا كانت المادة مدعومة، فاسم الجهة الراعية شرط لا يُتجاوز.
                </p>

                <h2>المسؤولية</h2>
                <p>
                    كل مادة تحمل اسم كاتبها وتاريخ نشرها وتاريخ آخر تحديث. وحين نخطئ نصحّح علنًا وفق
                    <a href="{{ route('web.corrections', $locale) }}" class="text-gold-ink underline">سياسة التصحيح</a>،
                    ولا نحذف بصمت.
                </p>

                <h2>الخبراء والمصادر</h2>
                <p>
                    نُميّز بين من يكتب لنا ومن نقتبس عنه. الخبير الذي يظهر في موادنا شخص حقيقي وافق على
                    ذلك، ولا نستخدم اسمًا أو صفة دون إذن.
                </p>
            </div>
        </div>
    </x-ui.container>
</x-layout.public>
