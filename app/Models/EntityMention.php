<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EntityRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * The edge in the content graph. Doubly polymorphic: any content type on one
 * side, any entity type on the other.
 */
class EntityMention extends Model
{
    use HasFactory;

    // A mention is created or deleted, never edited.
    public const UPDATED_AT = null;

    protected $fillable = [
        'mentionable_type',
        'mentionable_id',
        'entity_type',
        'entity_id',
        'role',
        'prominence',
    ];

    protected function casts(): array
    {
        return [
            'role' => EntityRole::class,
            'prominence' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function mentionable(): MorphTo
    {
        return $this->morphTo();
    }

    public function entity(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeOfRole($query, EntityRole $role)
    {
        return $query->where('role', $role);
    }
}
