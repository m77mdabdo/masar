<?php

declare(strict_types=1);

use Illuminate\View\ComponentAttributeBag;

/**
 * The article page's only inverted ground. CLAUDE.md §5: a number the product
 * cannot verify must be labelled, dated, attributed, and must disappear rather
 * than degrade when its basis is missing — if it cannot meet all four, it does
 * not render.
 */
function keyNumbersHtml(array $numbers): string
{
    return (string) view('components.article.key-numbers', [
        'numbers' => $numbers,
        'attributes' => new ComponentAttributeBag,
    ])->render();
}

it('renders a figure that carries all four parts', function (): void {
    $html = keyNumbersHtml([[
        'value' => '18 مليار ريال',
        'label' => 'قيمة الاستثمارات',
        'source' => 'وزارة الاستثمار',
        'as_of' => '2026-03',
    ]]);

    expect($html)->toContain('18 مليار ريال')
        ->and($html)->toContain('قيمة الاستثمارات')
        ->and($html)->toContain('وزارة الاستثمار')
        ->and($html)->toContain('2026-03');
});

dataset('incomplete figures', [
    'no source' => [['value' => '18 مليار ريال', 'label' => 'قيمة الاستثمارات', 'as_of' => '2026-03']],
    'no date' => [['value' => '18 مليار ريال', 'label' => 'قيمة الاستثمارات', 'source' => 'وزارة الاستثمار']],
    'no label' => [['value' => '18 مليار ريال', 'source' => 'وزارة الاستثمار', 'as_of' => '2026-03']],
    'no value' => [['label' => 'قيمة الاستثمارات', 'source' => 'وزارة الاستثمار', 'as_of' => '2026-03']],
    'blank source' => [['value' => '18', 'label' => 'قيمة', 'source' => '', 'as_of' => '2026-03']],
]);

it('renders nothing at all for a figure short of any part', function (array $number): void {
    // Not "renders it without the source" — the whole band goes, because a
    // figure that cannot be checked is worse on the page than absent from it.
    expect(trim(keyNumbersHtml([$number])))->toBe('');
})->with('incomplete figures');

it('drops only the incomplete figure when a complete one is present', function (): void {
    $html = keyNumbersHtml([
        ['value' => '18 مليار ريال', 'label' => 'قيمة الاستثمارات', 'source' => 'وزارة الاستثمار', 'as_of' => '2026-03'],
        ['value' => '42%', 'label' => 'نمو سنوي'],
    ]);

    expect($html)->toContain('18 مليار ريال')
        ->and($html)->not->toContain('42%')
        ->and($html)->not->toContain('نمو سنوي');
});

it('gives the factory figures a source and a date, and a value that fits its label', function (): void {
    $units = collect(Database\Factories\Support\ArabicContent::KEY_NUMBERS)
        ->keyBy('label')
        ->map(fn (array $n): string => $n['unit']);

    for ($i = 0; $i < 25; $i++) {
        $numbers = App\Models\Article::factory()->make()->key_numbers;

        foreach ($numbers as $number) {
            expect($number)->toHaveKeys(['value', 'label', 'source', 'as_of'])
                ->and($number['source'])->not->toBeEmpty()
                ->and($number['as_of'])->toMatch('/^\d{4}-\d{2}$/');

            // The pairing, which is what was actually broken: a count must not
            // arrive denominated in riyals.
            $expected = $units[$number['label']];
            $value = $number['value'];

            match ($expected) {
                'percent' => expect($value)->toEndWith('%'),
                'sar_billions' => expect($value)->toContain('مليار ريال'),
                default => expect($value)->toMatch('/^[\d,]+$/'),
            };
        }
    }
});
