<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Intelligence\RecordSourceResult;
use App\Actions\Intelligence\StoreSourceItem;
use App\Jobs\CheckSourceJob;
use App\Models\Source;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Dispatches a poll for every source that is due.
 *
 * `--once` runs them inline and reports what happened, which is what makes this
 * debuggable: a source that is failing should be visible from a terminal
 * without reading a queue dashboard.
 */
class CheckSources extends Command
{
    protected $signature = 'masar:check-sources
        {--once : Run synchronously and report, instead of dispatching to the queue}
        {--source= : Check one source by id, ignoring whether it is due}';

    protected $description = 'Poll every monitored source that is due for a check';

    public function handle(): int
    {
        $sources = $this->option('source') !== null
            ? Source::query()->whereKey($this->option('source'))->get()
            : Source::query()->due()->get();

        if ($sources->isEmpty()) {
            $this->info('No sources due.');

            return self::SUCCESS;
        }

        if (! $this->option('once')) {
            $sources->each(fn (Source $source) => CheckSourceJob::dispatch($source->getKey()));
            $this->info("Dispatched {$sources->count()} source checks.");

            return self::SUCCESS;
        }

        $rows = [];

        foreach ($sources as $source) {
            $before = $source->intelligenceItems()->count();

            // Synchronously, on purpose: --once exists to be watched.
            app(CheckSourceJob::class, ['sourceId' => $source->getKey()])->handle(
                app(StoreSourceItem::class),
                app(RecordSourceResult::class),
            );

            $source->refresh();

            $rows[] = [
                $source->name,
                $source->healthState(),
                $source->intelligenceItems()->count() - $before,
                $source->consecutive_failures,
                Str::limit((string) $source->error_message, 40) ?: '—',
            ];
        }

        $this->table(['Source', 'Health', 'New', 'Failures', 'Error'], $rows);

        return self::SUCCESS;
    }
}
