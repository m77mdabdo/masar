<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Every number the market surfaces render.
 *
 * There is no feed and no licence. Each figure is typed in by an editor and
 * carries a date and a source, because CLAUDE.md §5 is absolute about it: a
 * figure without a source and a date does not publish. A fabricated index print
 * on a business page is a false market statistic put in front of a reader who
 * has no way to check it.
 *
 * So every accessor can return nothing, and every caller has to handle that.
 * The empty state is the honest state, not an edge case.
 */
final class MarketFigures
{
    /**
     * The headline index — the one the chart is of.
     *
     * @return array{label: string, value: string, change: ?float, series: array<int, float>}|null
     */
    public function index(): ?array
    {
        $row = (array) setting('market.index', []);

        if (blank($row['label'] ?? null) || blank($row['value'] ?? null)) {
            return null;
        }

        return [
            'label' => (string) $row['label'],
            'value' => (string) $row['value'],
            'change' => isset($row['change']) && $row['change'] !== '' ? (float) $row['change'] : null,
            'series' => $this->series($row['series'] ?? null),
        ];
    }

    /** The instrument grid beside the chart. */
    public function instruments(): Collection
    {
        return $this->rows('market.instruments');
    }

    /** The cells in the ticker band. */
    public function ticker(): Collection
    {
        return $this->rows('market.ticker');
    }

    /** Regional movement in the intelligence panel. */
    public function pulse(): Collection
    {
        return $this->rows('market.pulse');
    }

    /** The four-cell data grid. */
    public function data(): Collection
    {
        return $this->rows('market.data');
    }

    /**
     * When the editor says these figures were true.
     *
     * Rendered wherever a figure is, without exception. A number with no "as of"
     * is a number a reader will assume is live.
     */
    public function asOf(): ?Carbon
    {
        $value = setting('market.as_of');

        return blank($value) ? null : Carbon::parse((string) $value);
    }

    /** Who published them. Displayed beside the timestamp. */
    public function source(): ?string
    {
        $value = setting('market.source');

        return blank($value) ? null : (string) $value;
    }

    /**
     * A figure only renders when it is dated. Without `as_of` the whole set is
     * treated as absent, so a half-filled settings screen cannot put an undated
     * number on the page.
     */
    public function isPublishable(): bool
    {
        return $this->asOf() !== null;
    }

    /**
     * @return Collection<int, array{label: string, value: string, change: ?float, series: array<int, float>}>
     */
    private function rows(string $key): Collection
    {
        if (! $this->isPublishable()) {
            return collect();
        }

        return collect((array) setting($key, []))
            ->filter(fn ($row): bool => filled($row['label'] ?? null) && filled($row['value'] ?? null))
            ->map(fn (array $row): array => [
                'label' => (string) $row['label'],
                'value' => (string) $row['value'],
                'change' => isset($row['change']) && $row['change'] !== '' ? (float) $row['change'] : null,
                // The sparkline's shape. Editor-entered like everything else
                // here: a line drawn from nothing would be a picture of a trend
                // nobody recorded.
                'series' => $this->series($row['series'] ?? null),
            ])
            ->values();
    }

    /**
     * The chart's points, as an editor types them: comma-separated numbers.
     *
     * A shape, not a claim to precision — which is why the chart is drawn
     * without a value axis. Fewer than two points is not a line.
     *
     * @return array<int, float>
     */
    private function series(mixed $raw): array
    {
        $points = collect(preg_split('/[,\s]+/', trim((string) $raw)) ?: [])
            ->filter(fn (string $p): bool => is_numeric($p))
            ->map(fn (string $p): float => (float) $p)
            ->values()
            ->all();

        return count($points) >= 2 ? $points : [];
    }
}
