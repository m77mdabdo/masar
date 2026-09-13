{{-- Structural preview of a composed layout. Not the final front-end design
     (that is TASK 05) — it renders the same resolved content, in order, so an
     editor can check the shape of the page before it goes live. --}}
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>معاينة: {{ $layout->name }} — مسار</title>
    <link rel="stylesheet" href="/fonts/fonts.css">
    <style>
        :root { --g-900:#0E2A1C; --mint:#7FB69A; --cream:#F6F4EF; --line:#E2DED4; --ink:#14181A; --ink-3:#7C848A; --gold:#C9A063; }
        * { box-sizing: border-box; }
        body {
            margin: 0; background: var(--cream); color: var(--ink);
            font-family: 'IBM Plex Sans Arabic', system-ui, sans-serif;
            font-variant-numeric: lining-nums; line-height: 1.95;
        }
        .wrap { max-width: 1100px; margin-inline: auto; padding-inline: 20px; padding-block: 24px; }
        .bar { background: var(--g-900); color: var(--cream); padding-block: 12px; }
        .bar .wrap { padding-block: 0; display: flex; align-items: center; gap: 12px; }
        h1 { font-family: 'Readex Pro', system-ui, sans-serif; font-size: 20px; margin: 0; }
        .tag { background: var(--mint); color: var(--g-900); border-radius: 4px; padding: 2px 8px; font-size: 12px; }
        section { border-top: 1px solid var(--line); padding-block: 24px; }
        h2 { font-family: 'Readex Pro', system-ui, sans-serif; font-size: 18px; margin: 0 0 4px; }
        .meta { color: var(--ink-3); font-size: 12px; margin-bottom: 14px; }
        .grid { display: grid; gap: 14px; grid-template-columns: repeat(auto-fill, minmax(230px, 1fr)); }
        .card { background: #fff; border: 1px solid var(--line); border-radius: 10px; padding: 12px; }
        .card h3 { font-size: 14px; margin: 0 0 6px; line-height: 1.6; }
        .card .sub { color: var(--ink-3); font-size: 11px; display: flex; gap: 8px; flex-wrap: wrap; }
        .empty { color: #b4522e; font-size: 13px; }
        .ltr { direction: ltr; unicode-bidi: isolate; }
        .hero { aspect-ratio: 16/9; background: var(--line); border-radius: 6px; margin-bottom: 8px; display: grid; place-items: center; color: var(--ink-3); font-size: 11px; }
    </style>
</head>
<body>
    <div class="bar">
        <div class="wrap">
            <h1>مسار</h1>
            <span class="tag">معاينة — {{ $layout->name }}</span>
            @unless ($layout->is_active)
                <span class="tag" style="background: var(--gold);">غير نشط</span>
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
                    <p class="empty">لا يوجد محتوى مطابق لهذا القسم.</p>
                @else
                    <div class="grid">
                        @foreach ($section['items'] as $item)
                            <article class="card">
                                <div class="hero">
                                    {{ $item instanceof \App\Models\Article && $item->hero_media_id ? 'صورة الغلاف' : 'بلا غلاف' }}
                                </div>
                                <h3>{{ $item->title }}</h3>
                                <div class="sub">
                                    @if ($item instanceof \App\Models\Article)
                                        <span>{{ $item->category?->name }}</span>
                                        <span class="ltr">{{ $item->published_at?->format('Y-m-d') }}</span>
                                    @else
                                        <span>فرصة</span>
                                        <span>{{ $item->potential?->label() }}</span>
                                    @endif
                                </div>
                            </article>
                        @endforeach
                    </div>
                @endif
            </section>
        @empty
            <p class="empty">لا توجد أقسام ظاهرة في هذا التخطيط.</p>
        @endforelse
    </div>
</body>
</html>
