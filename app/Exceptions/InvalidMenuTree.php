<?php

declare(strict_types=1);

namespace App\Exceptions;

use DomainException;

class InvalidMenuTree extends DomainException
{
    /**
     * @param  array<int, string>  $reasons  Arabic, editor-facing.
     */
    public function __construct(public readonly array $reasons)
    {
        parent::__construct('Menu tree rejected: '.count($reasons).' problem(s).');
    }

    /**
     * @return array<int, string>
     */
    public function reasons(): array
    {
        return $this->reasons;
    }
}
