@php $locale = app()->getLocale(); @endphp

{{--
    This page describes an editorial commitment, not a mechanism.

    It used to describe one: a correction line appended to the article with the
    old wording, the new wording and the date, and a prominent placement for
    material errors. None of that exists in the schema — there is no corrections
    table, no correction field on articles, and nothing renders one. A reader
    had no way to know that, which is precisely what made it a false claim
    rather than an aspiration.

    Every sentence here is now in the first person plural and describes what we
    do. When the corrections feature is built (DEFERRED.md), this page can go
    back to describing the mechanism — and the sentence that describes it will
    owe a test.
--}}
<x-layout.public title="سياسة التصحيح" description="كيف نصحّح أخطاءنا، ومتى، وأين يظهر التصحيح.">
    <x-ui.container class="py-10">
        <div class="mx-auto max-w-[760px]">
            <header class="mb-10 border-b border-line pb-6">
                <h1 class="font-display text-3xl font-semibold leading-tight text-g-950 sm:text-4xl">سياسة التصحيح</h1>
                <p class="mt-4 text-lg leading-relaxed text-ink-3">
                    الخطأ الذي لا يُصحَّح علنًا يبقى منشورًا. هذه سياستنا في التصحيح، وهي جزء من
                    <a href="{{ route('web.editorial-standards', $locale) }}" class="text-gold-ink underline">معاييرنا التحريرية</a>.
                </p>
            </header>

            <div class="prose-masar">
                <h2>ما الذي نعدّه خطأً</h2>
                <p>
                    كل ما يغيّر فهم القارئ للمادة: رقم غير صحيح، اسم أو صفة خاطئة، نسبة تصريح إلى غير
                    صاحبه، تاريخ أو مصدر غير دقيق، أو سياق ناقص يقلب المعنى. الخطأ المطبعي الذي لا يغيّر
                    المعنى نصحّحه دون إشعار.
                </p>

                <h2>كيف نصحّح</h2>
                <p>
                    نصحّح النص نفسه، ونكتب في المادة ما كان مكتوبًا وما صار ومتى جرى التعديل. لا نحذف
                    المادة ولا نعدّلها بصمت: النسخة المنشورة سابقًا جزء من السجل، وإخفاء الخطأ أسوأ من
                    الخطأ. وإذا كان الخطأ جوهريًا — رقم يقود إلى قرار، أو نسبة قول إلى شخص لم يقله —
                    نضع التصحيح حيث يراه القارئ قبل أن يقرأ المادة، لا في آخرها.
                </p>
                <p class="text-sm text-ink-3">
                    نقول هذا بوصفه التزامًا تحريريًا نلتزم به نحن، لا بوصفه إجراءً يفرضه النظام. نحن في
                    طور بناء واجهة تعرض سجل التصحيحات على المادة نفسها؛ وإلى أن تكتمل، يظهر على كل مادة
                    عُدِّلت بعد نشرها تاريخُ آخر تحديث، والتصحيح مكتوب داخل النص.
                </p>

                <h2>متى</h2>
                <p>
                    نصحّح فور التحقق. لا ننتظر دورة تحرير، ولا نؤجل إلى نهاية اليوم. التحقق نفسه قد
                    يستغرق وقتًا، وحين يستغرق نقول ذلك للمُبلِّغ بدل أن نصمت.
                </p>

                <h2>كيف تُبلغنا</h2>
                <p>
                    راسلنا عبر <a href="{{ route('web.contact', $locale) }}" class="text-gold-ink underline">صفحة التواصل</a>
                    مع رابط المادة وموضع الخطأ وما تعتقد أنه الصواب. البلاغ الذي يحمل مصدرًا نعالجه أسرع،
                    لكن غياب المصدر لا يعني تجاهل البلاغ.
                </p>

                <h2>ما لا نعدّه تصحيحًا</h2>
                <p>
                    الاختلاف مع التحليل أو مع زاوية التغطية ليس خطأً. نرحّب به، ونعالجه بالنشر لا بالحذف.
                    وتحديث مادة بتطوّر جديد ليس تصحيحًا، بل تحديث نؤرّخه بوضوح على المادة.
                </p>
            </div>
        </div>
    </x-ui.container>
</x-layout.public>
