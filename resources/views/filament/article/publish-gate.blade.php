{{-- The publish gate, always visible on the edit page.

     An editor must never click publish and simply be told no. Each unmet rule
     links to the tab that fixes it, so the answer to "why can't I publish?" is
     one click from the question. --}}
@php
    $failures = $this->gateFailures();
    $passes = $failures === [];
@endphp

<div
    @class([
        'fi-section rounded-xl border bg-white p-4 dark:bg-gray-900',
        'masar-gate-pass border-gray-200 dark:border-white/10' => $passes,
        'masar-gate-fail border-gray-200 dark:border-white/10' => ! $passes,
    ])
>
    <div class="mb-3 flex items-center gap-2">
        @if ($passes)
            <x-filament::icon icon="heroicon-o-check-circle" class="h-5 w-5 text-[#1E5E3F]" />
            <span class="text-sm font-semibold text-[#1E5E3F]">جاهزة للنشر</span>
        @else
            <x-filament::icon icon="heroicon-o-exclamation-triangle" class="h-5 w-5 text-[#B4522E]" />
            <span class="text-sm font-semibold text-[#B4522E]">
                {{ count($failures) }} من متطلبات النشر غير مكتملة
            </span>
        @endif
    </div>

    @if ($passes)
        <p class="text-sm text-gray-600 dark:text-gray-400">
            استوفت المادة كل شروط بوابة النشر.
        </p>
    @else
        <ul class="space-y-2">
            @foreach ($failures as $failure)
                <li class="flex items-start gap-2 text-sm text-gray-700 dark:text-gray-300">
                    <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-[#B4522E]"></span>
                    <span>
                        {{ $failure['message'] }}
                        <button
                            type="button"
                            wire:click="focusGateTab('{{ $failure['tab'] }}')"
                            class="ms-1 font-medium text-[#1E5E3F] underline underline-offset-2 hover:text-[#0E2A1C]"
                        >
                            {{ $failure['tab_label'] }}
                        </button>
                    </span>
                </li>
            @endforeach
        </ul>
    @endif
</div>
