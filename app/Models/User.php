<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\IsDemoContent;
use App\Support\Locales;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\MissingAttributeException;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use SensitiveParameter;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery, HasLocalePreference
{
    use HasFactory;
    use HasRoles;
    use IsDemoContent;
    use Notifiable;

    protected $fillable = [
        'name',
        'email',
        'photo_path',
        'password',
        'locale',
        'name_translations',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'app_authentication_secret',
        'app_authentication_recovery_codes',
    ];

    protected function casts(): array
    {
        return [
            'name_translations' => 'array',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'app_authentication_secret' => 'encrypted',
            'app_authentication_recovery_codes' => 'encrypted:array',
        ];
    }

    // ------------------------------------------------------------------
    // Filament
    // ------------------------------------------------------------------

    /**
     * Staff join by invitation, so panel access is a permission question, not an
     * email-domain one. Anyone who can view an article belongs in the workspace;
     * the 2FA requirement is enforced separately by middleware, because a user
     * who simply has not enrolled yet needs a redirect to the setup page, not a
     * flat "access denied".
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->can('article.view');
    }

    // ------------------------------------------------------------------
    // Two-factor (TOTP)
    // ------------------------------------------------------------------

    public function getAppAuthenticationSecret(): ?string
    {
        return $this->app_authentication_secret;
    }

    public function saveAppAuthenticationSecret(#[SensitiveParameter] ?string $secret): void
    {
        $this->app_authentication_secret = $secret;
        $this->two_factor_confirmed_at = $secret === null ? null : now();
        $this->save();
    }

    public function getAppAuthenticationHolderName(): string
    {
        return $this->email;
    }

    /**
     * @return array<int, string>|null
     */
    public function getAppAuthenticationRecoveryCodes(): ?array
    {
        return $this->app_authentication_recovery_codes;
    }

    /**
     * @param  array<int, string>|null  $codes
     */
    public function saveAppAuthenticationRecoveryCodes(#[SensitiveParameter] ?array $codes): void
    {
        $this->app_authentication_recovery_codes = $codes;
        $this->save();
    }

    public function hasTwoFactorEnabled(): bool
    {
        return filled($this->app_authentication_secret);
    }

    /**
     * Anyone who can put words in front of readers must hold a second factor.
     */
    public function requiresTwoFactor(): bool
    {
        return $this->can('article.publish');
    }

    // ------------------------------------------------------------------
    // Relationships
    // ------------------------------------------------------------------

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

    /**
     * Notifications and mail reach each person in their own panel language:
     * Laravel renders a notification inside this locale for this recipient,
     * so a source alert sent by a worker is not in whatever locale the queue
     * happened to boot with.
     */
    public function preferredLocale(): string
    {
        return Locales::exists((string) $this->locale) ? (string) $this->locale : Locales::default();
    }

    /**
     * The byline in the page's edition (users.name_translations), else `name`.
     *
     * A column-limited load (`author:id,name`) leaves the translations out,
     * and the fallback then prints the Arabic name on every card of every
     * other edition with no error. That shipped on all listing pages, so a
     * missing column is refused instead of read as "no translation".
     */
    public function getDisplayNameAttribute(): string
    {
        if ($this->exists && ! array_key_exists('name_translations', $this->getAttributes())) {
            throw new MissingAttributeException($this, 'name_translations');
        }

        return (string) (($this->name_translations ?? [])[app()->getLocale()] ?? $this->name);
    }
}
