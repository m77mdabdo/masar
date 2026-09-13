@php $locale = app()->getLocale(); @endphp

<x-layout.public title="عن مسار" description="منصة إعلامية عربية للأعمال والاقتصاد، بتركيز على السوق السعودية.">
    <x-ui.container class="py-10">
        <div class="mx-auto max-w-[760px]">
            <header class="mb-10 border-b border-line pb-6">
                <h1 class="font-display text-3xl font-semibold leading-tight text-g-950 sm:text-4xl">عن مسار</h1>
                <p class="mt-4 text-lg leading-relaxed text-ink-3">{{ setting('identity.tagline') }}</p>
            </header>

            <div class="prose-masar">
                <p>
                    مسار منصة إعلامية عربية تغطي الأعمال والاقتصاد والأسواق والفرص، بتركيز أساسي
                    على السوق السعودية، وتغطية خليجية وعالمية حين تمس هذه السوق مباشرة.
                </p>

                <h2>ما الذي يميّزنا</h2>
                <p>
                    معظم التغطية الاقتصادية تتوقف عند «ماذا حدث». نحن نكمل الطريق: لماذا يهم، من
                    المتأثر، وأين الفرصة. هذا المسار هو ما تقوم عليه كل مادة ننشرها.
                </p>

                <h2>لمن نكتب</h2>
                <p>
                    لمن يتخذ قرارًا: مؤسس شركة، مسؤول استثمار، مدير قطاع، أو من يفكر في دخول سوق
                    جديدة. نكتب لمن سيتصرّف بناءً على ما يقرأ، لا لمن يتابع الأخبار فقط.
                </p>
            </div>

            <div class="mt-10 grid gap-4 sm:grid-cols-2">
                <a href="{{ route('web.editorial-standards', $locale) }}"
                   class="rounded-xl border border-line bg-white p-5 transition hover:border-g-600">
                    <h2 class="font-display text-lg font-semibold text-g-900">معايير التحرير</h2>
                    <p class="mt-1 text-sm text-ink-3">كيف نتحقق، وكيف نصحّح.</p>
                </a>
                <a href="{{ route('web.contact', $locale) }}"
                   class="rounded-xl border border-line bg-white p-5 transition hover:border-g-600">
                    <h2 class="font-display text-lg font-semibold text-g-900">اتصل بنا</h2>
                    <p class="mt-1 text-sm text-ink-3">التحرير، الإعلانات، التوظيف.</p>
                </a>
            </div>
        </div>
    </x-ui.container>
</x-layout.public>
