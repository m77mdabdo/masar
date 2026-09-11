<?php

declare(strict_types=1);

namespace App\Exceptions;

use DomainException;

class PublishGateFailed extends DomainException
{
    /**
     * @param  array<int, string>  $reasons  Arabic, editor-facing, actionable.
     */
    public function __construct(public readonly array $reasons)
    {
        parent::__construct(
            'Publish gate rejected the article: '.count($reasons).' unmet requirement(s).',
        );
    }

    /**
     * @return array<int, string>
     */
    public function reasons(): array
    {
        return $this->reasons;
    }
}
