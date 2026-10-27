<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Support\DemoContent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A model whose rows may be demo content.
 *
 * `is_demo` is never fillable: nothing an editor submits can set or clear it.
 * It is written once, at creation, by DemoContent::record().
 */
trait IsDemoContent
{
    public static function bootIsDemoContent(): void
    {
        static::creating(function (Model $model): void {
            if (DemoContent::isRecording()) {
                $model->setAttribute('is_demo', true);
            }
        });
    }

    public function initializeIsDemoContent(): void
    {
        $this->mergeCasts(['is_demo' => 'boolean']);
    }

    public function scopeDemo(Builder $query): Builder
    {
        return $query->where($this->qualifyColumn('is_demo'), true);
    }
}
