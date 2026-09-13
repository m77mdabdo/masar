@props(['section', 'figures', 'href' => null])
@php $rows = $figures->data(); @endphp
<section aria-labelledby="rail-data-heading">
    <x-ui.section-rule :title="$section['title']" id="rail-data-heading" :tight="true" />

    @if ($rows->isNotEmpty())
        <div class="grid grid-cols-2 gap-px border border-line bg-line">
            @foreach ($rows as $row)
                <div class="bg-white p-4">
                    <div class="font-display text-[0.65rem] font-semibold uppercase tracking-[0.1em] text-ink-3">{{ $row['label'] }}</div>
                    <x-data.figure :value="$row['value']" :change="$row['change']" size="lg" class="mt-2" />
                </div>
            @endforeach
        </div>
        <x-data.as-of :figures="$figures" class="mt-3" />
    @else
        <x-data.empty label="لم تُدخل مؤشرات لوحة البيانات بعد. لا نعرض رقمًا بلا مصدر وتاريخ." />
    @endif

    @if ($href)
        <a href="{{ $href }}" class="mt-4 flex items-center justify-center gap-2 rounded-full border border-line px-5 py-2.5 text-xs font-medium text-g-900 transition hover:border-g-600">
            افتح مركز البيانات
            <span aria-hidden="true" class="inline-block rtl:-scale-x-100">→</span>
        </a>
    @endif
</section>
