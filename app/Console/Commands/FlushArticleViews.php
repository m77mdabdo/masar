<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\ViewCounter;
use Illuminate\Console\Command;

class FlushArticleViews extends Command
{
    protected $signature = 'masar:flush-views';

    protected $description = 'Write buffered article reads from Redis into article_views';

    public function handle(ViewCounter $views): int
    {
        $written = $views->flush();

        $this->info($written === 0 ? 'No buffered reads.' : "Wrote {$written} article-day row(s).");

        return self::SUCCESS;
    }
}
