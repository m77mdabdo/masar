<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory;
    use HasRoles;
    use Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Articles where this user holds the lead byline.
     */
    public function articles(): HasMany
    {
        return $this->hasMany(Article::class, 'author_id');
    }

    public function editedArticles(): HasMany
    {
        return $this->hasMany(Article::class, 'editor_id');
    }

    public function factCheckedArticles(): HasMany
    {
        return $this->hasMany(Article::class, 'fact_checker_id');
    }

    /**
     * Articles where this user is credited as a co-author rather than the lead.
     */
    public function coAuthoredArticles(): BelongsToMany
    {
        return $this->belongsToMany(Article::class, 'article_author')
            ->withPivot('role');
    }

    /**
     * Permission-based, never role-based: a role is a bundle of permissions that
     * editorial can reshape without a deploy.
     */
    public function canPublish(): bool
    {
        return $this->hasPermissionTo('article.publish');
    }
}
