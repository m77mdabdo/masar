@props(['headers' => [], 'caption' => null])
{{-- Tables are the one thing allowed to scroll sideways, inside their own
     container — the page body never does. --}}
<div class="not-prose my-8 overflow-x-auto rounded-xl border border-line bg-white">
    <table class="nums-tabular w-full text-sm">
        @if ($caption)<caption class="p-3 text-start text-xs text-ink-3">{{ $caption }}</caption>@endif
        @if ($headers !== [])
            <thead class="border-b border-line bg-cream/60">
                <tr>
                    @foreach ($headers as $header)
                        <th scope="col" class="px-4 py-3 text-start font-medium text-g-900">{{ $header }}</th>
                    @endforeach
                </tr>
            </thead>
        @endif
        <tbody class="divide-y divide-line">{{ $slot }}</tbody>
    </table>
</div>
