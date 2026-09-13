@props(['name', 'code' => null, 'value' => null, 'change' => null])
<tr>
    <td class="px-4 py-3">
        <div class="font-medium">{{ $name }}</div>
        @if ($code)<div class="ltr-isolate text-xs text-ink-3">{{ $code }}</div>@endif
    </td>
    <td class="ltr-isolate px-4 py-3">{{ $value ?? '—' }}</td>
    <td @class(['px-4 py-3 ltr-isolate', 'text-g-600' => ($change ?? 0) >= 0, 'text-gold-ink' => ($change ?? 0) < 0])>
        {{ $change === null ? '—' : (($change >= 0 ? '+' : '').$change.'%') }}
    </td>
</tr>
