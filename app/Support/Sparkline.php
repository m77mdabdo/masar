<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The path for an editor-entered price shape.
 *
 * Two things here are not cosmetic.
 *
 * The amplitude is damped for a series that barely moves. Normalising every
 * series to the full box means a pegged rate — where the whole range is a
 * rounding error at its own level — is drawn as wild movement, which is a
 * false picture of the most stable instrument on the band. A series whose
 * range is 4% or more of its own level gets the full height; anything flatter
 * gets proportionally less, so a peg reads as a peg.
 *
 * The curve is Catmull-Rom through the points, converted to cubic Béziers. It
 * passes through every point an editor typed rather than approximating them —
 * a smoothing that moved the points would be drawing a different series from
 * the one entered.
 */
final class Sparkline
{
    /**
     * The range-over-level at which a series earns the whole box.
     *
     * 1.5% is a normal trading day. The response is compressive — the square
     * root of the ratio against this reference — because a linear map cannot
     * serve both ends of what this chart has to draw: an index moving 1.2% and
     * a pegged rate moving 0.016% are three orders of magnitude apart, and a
     * straight line through them either flattens the index or shouts the peg.
     */
    private const FULL_AMPLITUDE_AT = 0.015;

    /**
     * @param  array<int, float>  $points
     */
    public static function path(array $points, float $width, float $height, float $stroke = 1.4): string
    {
        $coords = self::coordinates($points, $width, $height, $stroke);

        if ($coords === []) {
            return '';
        }

        if (count($coords) === 1) {
            return '';
        }

        $d = 'M'.self::point($coords[0]);
        $last = count($coords) - 1;

        for ($i = 0; $i < $last; $i++) {
            $p0 = $coords[max(0, $i - 1)];
            $p1 = $coords[$i];
            $p2 = $coords[$i + 1];
            $p3 = $coords[min($last, $i + 2)];

            // Catmull-Rom control points, at the standard 1/6 tension.
            $c1 = [$p1[0] + ($p2[0] - $p0[0]) / 6, $p1[1] + ($p2[1] - $p0[1]) / 6];
            $c2 = [$p2[0] - ($p3[0] - $p1[0]) / 6, $p2[1] - ($p3[1] - $p1[1]) / 6];

            $d .= 'C'.self::point($c1).' '.self::point($c2).' '.self::point($p2);
        }

        return $d;
    }

    /**
     * The same path closed to the baseline, for the filled chart.
     *
     * @param  array<int, float>  $points
     */
    public static function area(array $points, float $width, float $height, float $stroke = 1.4): string
    {
        $path = self::path($points, $width, $height, $stroke);

        if ($path === '') {
            return '';
        }

        return $path.'L'.round($width, 2).','.round($height, 2).'L0,'.round($height, 2).'Z';
    }

    /**
     * @param  array<int, float>  $points
     * @return array<int, array{0: float, 1: float}>
     */
    private static function coordinates(array $points, float $width, float $height, float $stroke): array
    {
        $points = array_values(array_filter($points, 'is_numeric'));

        if (count($points) < 2) {
            return [];
        }

        $min = min($points);
        $max = max($points);
        $span = $max - $min;
        $mid = ($min + $max) / 2;

        // Keep the stroke inside the box: a path drawn to the very edge is
        // clipped by half its own width.
        $usable = max(0.0, ($height - $stroke) / 2);
        $centre = $height / 2;

        $level = max(abs(array_sum($points) / count($points)), 1e-9);
        $ratio = $span <= 0.0 ? 0.0 : $span / $level;
        $amplitude = $ratio <= 0.0 ? 0.0 : min(1.0, sqrt($ratio / self::FULL_AMPLITUDE_AT));

        $step = $width / (count($points) - 1);
        $coords = [];

        foreach ($points as $i => $p) {
            $y = $span <= 0.0
                ? $centre
                : $centre - (($p - $mid) / ($span / 2)) * $amplitude * $usable;

            $coords[] = [$i * $step, $y];
        }

        return $coords;
    }

    /**
     * @param  array{0: float, 1: float}  $p
     */
    private static function point(array $p): string
    {
        return round($p[0], 2).','.round($p[1], 2);
    }
}
