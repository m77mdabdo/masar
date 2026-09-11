<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\ArticleStatus;
use DomainException;

class InvalidStatusTransition extends DomainException
{
    /**
     * @param  array<int, ArticleStatus>  $allowed
     */
    public function __construct(
        public readonly ArticleStatus $from,
        public readonly ArticleStatus $to,
        public readonly array $allowed = [],
    ) {
        parent::__construct(sprintf(
            'Cannot move an article from "%s" to "%s". Allowed: %s.',
            $from->value,
            $to->value,
            $allowed === []
                ? 'none'
                : implode(', ', array_column($allowed, 'value')),
        ));
    }

    public static function between(ArticleStatus $from, ArticleStatus $to): self
    {
        return new self($from, $to, $from->allowedTransitions());
    }

    /**
     * Arabic message for the editor. The exception message itself stays English
     * because it is written to logs, not to a screen.
     */
    public function forEditor(): string
    {
        return sprintf(
            'لا يمكن نقل المادة من "%s" إلى "%s".',
            $this->from->label(),
            $this->to->label(),
        );
    }
}
