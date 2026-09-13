<?php

declare(strict_types=1);

namespace App\Services\Intelligence\Fetchers;

use App\Models\Source;
use App\Services\Intelligence\FetchResult;

interface Fetcher
{
    public function fetch(Source $source): FetchResult;
}
