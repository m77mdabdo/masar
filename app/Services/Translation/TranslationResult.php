<?php

declare(strict_types=1);

namespace App\Services\Translation;

final readonly class TranslationResult
{
    /**
     * @param  array<string, string>  $segments
     * @param  int|null  $billedCharacters  what the provider says it charged for, when it says
     */
    public function __construct(
        public array $segments,
        public ?int $billedCharacters = null,
    ) {}
}
