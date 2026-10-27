<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * The sponsored label was about to be removed from an article a reader has
 * already seen carrying it.
 *
 * The advertise page tells advertisers and readers that the label cannot be
 * taken off after publication. That sentence is a claim of prevention, so it
 * owes this guard — the rule in CLAUDE.md §5 under "What the site claims about
 * itself".
 */
class DisclosureCannotBeWithdrawn extends RuntimeException
{
    public static function forArticle(): self
    {
        return new self(
            'لا يمكن إزالة وسم «محتوى مدعوم» عن مادة منشورة. '
            .'إن كان الوسم قد وُضع خطأً، أعِد المادة إلى المسودة أولًا.'
        );
    }
}
