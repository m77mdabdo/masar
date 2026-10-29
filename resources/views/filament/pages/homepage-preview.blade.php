{{-- Structural preview of a composed layout. Not the final front-end design
     (that is TASK 05) — it renders the same resolved content, in order, so an
     editor can check the shape of the page before it goes live. --}}
<!DOCTYPE html>
<html lang="{{ \App\Support\Locales::tag(app()->getLocale()) }}" dir="{{ \App\Support\Locales::dir(app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('معاينة: :name', ['name' => $layout->name]) }} — MASAR</title>
    <link rel="stylesheet" href="/fonts/fonts.css">
    <link rel="stylesheet" href="{{ asset('css/homepage-preview.css') }}">
</head>
<body>
    <div class="bar">
        <div class="wrap">
            <h1>{{ app()->getLocale() === 'ar' ? 'مسار' : 'MASAR' }}</h1>
            <span class="tag">{{ __('معاينة: :name', ['name' => $layout->name]) }}</span>
            @unless ($layout->is_active)
                <span class="tag" style="background: var(--gold);">{{ __('غير نشط') }}</span>
            @endunless
        </div>
    </div>

    <div class="wrap">
        @forelse ($sections as $section)
            <section>
                <h2>{{ $section['title'] }}</h2>
                <div class="meta">
                    {{ $section['type'] }} · {{ $section['source'] }} · {{ $section['items']->count() }}/{{ $section['limit'] }}
                </div>

                @if ($section['items']->isEmpty())
                    <p class="empty">{{ __('لا يوجد محتوى مطابق لهذا القسم.') }}</p>
                @else
                    <div class="grid">
                        @foreach ($section['items'] as $item)
                            <article class="card">
                                <div class="hero">
                                    {{ $item instanceof \App\Models\Article && $item->hero_media_id ? __('صورة الغلاف') : __('بلا غلاف') }}
                                </div>
                                <h3>{{ $item->title }}</h3>
                                <div class="sub">
                                    @if ($item instanceof \App\Models\Article)
                                        <span>{{ $item->category?->name }}</span>
                                        <span class="ltr">{{ $item->published_at?->format('Y-m-d') }}</span>
                                    @else
                                        <span>{{ __('فرصة') }}</span>
                                        <span>{{ $item->potential?->label() }}</span>
                                    @endif
                                </div>
                            </article>
                        @endforeach
                    </div>
                @endif
            </section>
        @empty
            <p class="empty">{{ __('لا توجد أقسام ظاهرة في هذا التخطيط.') }}</p>
        @endforelse
    </div>
</body>
</html>
