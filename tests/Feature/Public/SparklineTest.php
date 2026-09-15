<?php

declare(strict_types=1);

use App\Support\Sparkline;

it('draws nothing from fewer than two points', function (): void {
    expect(Sparkline::path([], 46, 18))->toBe('')
        ->and(Sparkline::path([42], 46, 18))->toBe('');
});

it('passes through every point an editor typed', function (): void {
    // A curve that approximated the points would be a different series from
    // the one entered. The first and last coordinates must be exactly the ends.
    $path = Sparkline::path([10, 40, 20, 50], 46, 18, 1.4);

    expect($path)->toStartWith('M0,')
        ->and($path)->toContain('46,');
});

it('centres a series that does not move at all', function (): void {
    $path = Sparkline::path([50, 50, 50, 50], 46, 18, 1.4);

    // Height 18, so the centre line is y = 9 throughout.
    expect($path)->toStartWith('M0,9')
        ->and($path)->not->toContain(',0 ')
        ->and($path)->not->toContain(',18');
});

function sparkSwing(array $points, float $width = 46, float $height = 22): float
{
    // Includes the Bézier control points, which overshoot the data slightly.
    // That is fine here: what is asserted is the relationship between a peg and
    // a mover, and both are measured the same way.
    preg_match_all('/,(\d+(?:\.\d+)?)/', Sparkline::path($points, $width, $height, 1.4), $m);
    $ys = array_map('floatval', $m[1]);

    return max($ys) - min($ys);
}

it('draws a pegged rate as nearly flat and a trading day as a real move', function (): void {
    // The peg's real band: a 0.016% range at its own level.
    $peg = sparkSwing([3.7500, 3.7503, 3.7498, 3.7501, 3.7500, 3.7497, 3.7502, 3.7500]);

    // A normal day on an index: a 1.5% range.
    $day = sparkSwing([11150, 11120, 11165, 11210, 11255, 11238, 11205, 11290]);

    // The whole point of the damping. A linear map cannot hold both ends: at a
    // threshold that lets the index fill the box, the peg is a dead straight
    // line, and at one that keeps the peg visible, the index is flat.
    expect($day)->toBeGreaterThan(18.0)             // of a 22px box
        ->and($peg)->toBeLessThan(4.0)
        ->and($peg)->toBeGreaterThan(0.5)           // visible, not missing data
        ->and($peg)->toBeLessThan($day * 0.25);
});

it('gives a series that really moves the whole box', function (): void {
    expect(sparkSwing([34, 38, 43, 47, 44, 40, 43, 49, 54, 52, 58, 63], 46, 18))
        ->toBeGreaterThan(14.0);
});

it('closes the area path back to the baseline', function (): void {
    $area = Sparkline::area([10, 40, 20, 50], 46, 18, 2);

    expect($area)->toEndWith('L0,18Z');
});
