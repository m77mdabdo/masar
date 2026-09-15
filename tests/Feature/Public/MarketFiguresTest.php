<?php

declare(strict_types=1);

use App\Support\MarketFigures;
use App\Support\Settings;
use Illuminate\View\ComponentAttributeBag;

/**
 * CLAUDE.md §5: a figure without a source and a date does not publish. A
 * fabricated index print on a business page is a false market statistic put in
 * front of a reader who has no way to check it, so these are not style tests.
 */
function figures(): MarketFigures
{
    return app(MarketFigures::class);
}

function writeFigures(array $values): void
{
    app(Settings::class)->setMany($values);
}

it('renders nothing at all until an editor has dated the figures', function (): void {
    writeFigures([
        'market.ticker' => [['label' => 'تاسي', 'value' => '11,234.56', 'change' => 1.24]],
        'market.data' => [['label' => 'نمو', 'value' => '+4.6%']],
        'market.index' => ['label' => 'تاسي', 'value' => '11,234.56'],
    ]);

    // Every value is present. None of them is dated, so none of them publishes.
    expect(figures()->isPublishable())->toBeFalse()
        ->and(figures()->ticker())->toBeEmpty()
        ->and(figures()->data())->toBeEmpty()
        ->and(figures()->pulse())->toBeEmpty()
        ->and(figures()->instruments())->toBeEmpty();
});

it('publishes them once they carry a date', function (): void {
    writeFigures([
        'market.as_of' => '2026-09-13 16:00',
        'market.ticker' => [['label' => 'تاسي', 'value' => '11,234.56', 'change' => 1.24]],
    ]);

    expect(figures()->isPublishable())->toBeTrue()
        ->and(figures()->ticker())->toHaveCount(1)
        ->and(figures()->asOf()?->format('Y-m-d H:i'))->toBe('2026-09-13 16:00');
});

it('drops a row that is missing a label or a value', function (): void {
    writeFigures([
        'market.as_of' => '2026-09-13 16:00',
        'market.ticker' => [
            ['label' => 'تاسي', 'value' => '11,234.56'],
            ['label' => 'بلا قيمة', 'value' => ''],
            ['label' => '', 'value' => '99'],
        ],
    ]);

    expect(figures()->ticker())->toHaveCount(1);
});

it('shows the date and the source on the page wherever a figure appears', function (): void {
    writeFigures([
        'market.as_of' => '2026-09-13 16:00',
        'market.source' => 'جهة النشر',
        'market.ticker' => [['label' => 'تاسي', 'value' => '11,234.56', 'change' => 1.24]],
    ]);

    $html = (string) view('components.data.as-of', [
        'figures' => figures(),
        'tone' => 'light',
        'attributes' => new ComponentAttributeBag([]),
    ])->render();

    expect($html)->toContain('2026-09-13 16:00')->toContain('جهة النشر');
});

it('treats a chart series of fewer than two points as no chart', function (): void {
    writeFigures([
        'market.as_of' => '2026-09-13 16:00',
        'market.index' => ['label' => 'تاسي', 'value' => '11,234.56', 'series' => '42'],
    ]);

    expect(figures()->index()['series'])->toBe([]);
});

it('parses a series an editor typed with spaces or commas', function (): void {
    writeFigures([
        'market.as_of' => '2026-09-13 16:00',
        'market.index' => ['label' => 'تاسي', 'value' => '1', 'series' => '10, 20  30,40'],
    ]);

    expect(figures()->index()['series'])->toBe([10.0, 20.0, 30.0, 40.0]);
});

it('hardcodes no figure in any blade file', function (): void {
    // The rule is not "the seeder is honest"; it is that a number can never
    // reach the page except through a setting an editor controls.
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(resource_path('views')));
    $offenders = [];

    foreach ($files as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $markup = (string) file_get_contents($file->getPathname());

        // A thousands-separated number or a signed percentage sitting in markup.
        if (preg_match('/>\s*[+\-−]?\d{1,3}(,\d{3})+(\.\d+)?\s*</', $markup)
            || preg_match('/>\s*[+\-−]\d+(\.\d+)?%\s*</', $markup)) {
            $offenders[] = $file->getFilename();
        }
    }

    expect($offenders)->toBe([]);
});

it('never claims a precision the chart cannot support', function (): void {
    // The series is an editor-entered shape, so the chart is drawn without a
    // value axis. An axis would present typed points as measured ones.
    $markup = (string) file_get_contents(resource_path('views/components/data/sparkline.blade.php'));

    expect($markup)->not->toContain('<text')
        ->and($markup)->toContain('aria-hidden="true"');
});

/**
 * The sparkline's failure mode is silence: a chart drawn from points nobody
 * entered looks exactly like a chart drawn from points somebody did. So the
 * mechanism is tested, not only the picture.
 */
it('carries each ticker row its own shape points', function (): void {
    writeFigures([
        'market.as_of' => '2026-09-13 16:00',
        'market.ticker' => [
            ['label' => 'تاسي', 'value' => '11,234.56', 'change' => 1.24, 'series' => '41,36,47,43,55,51,63'],
        ],
    ]);

    expect(figures()->ticker()->first()['series'])->toBe([41.0, 36.0, 47.0, 43.0, 55.0, 51.0, 63.0]);
});

it('gives a row with no points an empty series rather than inventing one', function (): void {
    writeFigures([
        'market.as_of' => '2026-09-13 16:00',
        'market.ticker' => [
            ['label' => 'تاسي', 'value' => '11,234.56', 'change' => 1.24],
            ['label' => 'برنت', 'value' => '82.14', 'change' => -0.31, 'series' => ''],
            // One point is not a line.
            ['label' => 'الذهب', 'value' => '2,328.50', 'change' => 0.82, 'series' => '44'],
        ],
    ]);

    expect(figures()->ticker()->pluck('series')->all())->toBe([[], [], []]);
});

it('renders no chart for a row that has no points', function (): void {
    writeFigures([
        'market.as_of' => '2026-09-13 16:00',
        'market.ticker' => [
            ['label' => 'تاسي', 'value' => '11,234.56', 'change' => 1.24, 'series' => '41,36,47,43,55,51,63'],
            ['label' => 'برنت', 'value' => '82.14', 'change' => -0.31],
        ],
    ]);

    $html = view('components.layout.ticker', [
        'figures' => figures(),
        'href' => null,
        'attributes' => new ComponentAttributeBag,
    ])->render();

    // One row has points and one does not, so exactly one chart is drawn. The
    // element is a <path> since the line became a curve; what is asserted is
    // that a row with no points gets no chart, not which tag draws it.
    expect(substr_count($html, '<path d="M'))->toBe(1)
        ->and($html)->toContain('82.14');
});

it('keeps a flat series on the centre line instead of the floor', function (): void {
    $html = view('components.data.sparkline', [
        'points' => [50, 50, 50, 50],
        'height' => 15,
        'width' => 32,
        'stroke' => 1.3,
        'area' => false,
        'attributes' => new ComponentAttributeBag,
    ])->render();

    // A pegged currency has no span to scale against. Falling back to a divisor
    // of 1 pinned it to the bottom of the box, which reads as a collapse.
    expect($html)->toContain('0,7.5')->toContain('32,7.5');
});

it('carries the date and the source inside the ticker band itself', function (): void {
    writeFigures([
        'market.as_of' => '2026-09-13 16:00',
        'market.source' => 'جهة النشر',
        'market.ticker' => [
            ['label' => 'تاسي', 'value' => '11,234.56', 'change' => 1.24, 'series' => '41,36,47,43,55,51,63'],
        ],
    ]);

    // Rendering the as-of component on its own only proves the component works.
    // It did exactly that while the ticker had quietly lost its caption in a
    // refactor, and the suite stayed green: a figure was on the page with no
    // visible date beside it, which is the one thing the gate forbids. So the
    // assertion is on the surface that shows the figure, not on the partial.
    $html = view('components.layout.ticker', [
        'figures' => figures(),
        'href' => null,
        'attributes' => new ComponentAttributeBag,
    ])->render();

    expect($html)->toContain('11,234.56')
        ->and($html)->toContain('2026-09-13 16:00')
        ->and($html)->toContain('جهة النشر');
});
