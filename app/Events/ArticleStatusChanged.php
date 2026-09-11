<?php

declare(strict_types=1);

namespace App\Events;

use App\Enums\ArticleStatus;
use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ArticleStatusChanged
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Article $article,
        public readonly ArticleStatus $from,
        public readonly ArticleStatus $to,
        public readonly User $actor,
        public readonly ?string $note = null,
    ) {}
}
