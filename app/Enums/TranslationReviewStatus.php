<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * A translation file's review state. `Source` is the Arabic original: it is
 * what the others are reviewed against, not something reviewed itself.
 */
enum TranslationReviewStatus: string
{
    case Source = 'source';
    case PendingReview = 'pending_review';
    case Approved = 'approved';
}
