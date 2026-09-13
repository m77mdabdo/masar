<x-layout.public :title="$opportunity->title" :description="$opportunity->summary">
    <x-ui.container class="py-10">
        <article class="mx-auto max-w-[760px]">
            <div class="mb-4 flex flex-wrap gap-2">
                <x-ui.badge tone="accent">{{ $opportunity->potential?->label() }}</x-ui.badge>
                @if ($opportunity->industry?->name)<x-ui.tag>{{ $opportunity->industry->name }}</x-ui.tag>@endif
            </div>

            <h1 class="font-display text-3xl font-semibold leading-tight text-g-950 sm:text-4xl">{{ $opportunity->title }}</h1>
            <p class="mt-4 text-lg leading-relaxed text-ink-3">{{ $opportunity->summary }}</p>

            @if (filled($opportunity->requirements))
                <section class="mt-8 rounded-xl border border-line bg-white p-5">
                    <h2 class="mb-3 font-display text-lg font-semibold text-g-900">المتطلبات</h2>
                    <ul class="space-y-2">
                        @foreach ((array) $opportunity->requirements as $requirement)
                            <li class="flex gap-3 text-base"><span class="mt-2.5 h-1.5 w-1.5 shrink-0 rounded-full bg-g-600"></span>{{ $requirement }}</li>
                        @endforeach
                    </ul>
                </section>
            @endif

            <dl class="mt-8 grid gap-4 sm:grid-cols-2">
                @if ($opportunity->deadline)
                    <div class="rounded-xl border border-line bg-white p-4">
                        <dt class="text-xs text-ink-3">آخر موعد</dt>
                        <dd class="ltr-isolate nums-tabular mt-1 font-display text-lg">{{ $opportunity->deadline->format('Y-m-d') }}</dd>
                    </div>
                @endif
                @if ($opportunity->country?->name)
                    <div class="rounded-xl border border-line bg-white p-4">
                        <dt class="text-xs text-ink-3">النطاق</dt>
                        <dd class="mt-1 font-display text-lg">{{ $opportunity->country->name }}</dd>
                    </div>
                @endif
            </dl>

            @if ($opportunity->official_source_url)
                {{-- An opportunity without a verifiable official source is a
                     rumour; the link is the whole claim. --}}
                <x-ui.button :href="$opportunity->official_source_url" class="mt-8">المصدر الرسمي</x-ui.button>
            @endif
        </article>
    </x-ui.container>
</x-layout.public>
