@php
    $locale = app()->getLocale();
    $urls = app(App\Support\EntityUrl::class);
@endphp

<x-layout.public title="النشرة البريدية" description="ملخص أسبوعي لما تغيّر في السوق السعودية، ولماذا يهم.">
    <x-ui.container class="py-10">
        <div class="mx-auto max-w-[760px]">
            <header class="mb-10 border-b border-line pb-6">
                <h1 class="font-display text-3xl font-semibold leading-tight text-g-950 sm:text-4xl">
                    من المعلومة إلى الفرصة، كل أسبوع
                </h1>
                <p class="mt-4 text-lg leading-relaxed text-ink-3">
                    ملخص أسبوعي لما تغيّر فعليًا في السوق السعودية، ولماذا يهم لمن يتخذ قرارًا.
                </p>
            </header>

            <form method="GET" class="rounded-xl border border-line bg-white p-6">
                <label for="subscribe-email" class="mb-2 block text-sm font-medium">البريد الإلكتروني</label>
                <div class="flex flex-col gap-2 sm:flex-row">
                    <input
                        id="subscribe-email" name="email" type="email" required dir="ltr"
                        placeholder="name@example.com"
                        class="ltr-isolate min-w-0 flex-1 rounded-lg border border-line px-4 py-3 focus:border-g-600 focus:outline-none"
                    />
                    <x-ui.button type="submit" size="lg">اشترك</x-ui.button>
                </div>
                <p class="mt-3 text-xs text-ink-3">
                    رسالة واحدة أسبوعيًا. يمكنك إلغاء الاشتراك في أي وقت.
                </p>
            </form>

            @if ($issues->isNotEmpty())
                <section class="mt-12">
                    <x-ui.section-rule title="ملفات سابقة" />
                    <div class="grid gap-3 sm:grid-cols-2">
                        @foreach ($issues as $issue)
                            <a href="{{ route('web.issue.show', [$locale, $issue->slug]) }}"
                               class="rounded-xl border border-line bg-white p-4 transition hover:border-g-600">
                                <div class="font-display text-base font-medium text-g-950">{{ $issue->name }}</div>
                                <div class="nums-tabular mt-1 text-xs text-ink-3">{{ $issue->articles_count }} مادة</div>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif

            @if ($recent->isNotEmpty())
                <section class="mt-12">
                    <x-ui.section-rule title="ما نشرناه مؤخرًا" />
                    <div class="space-y-5">
                        @foreach ($recent as $article)
                            <x-article.card-row :article="$article" />
                        @endforeach
                    </div>
                </section>
            @endif
        </div>
    </x-ui.container>
</x-layout.public>
