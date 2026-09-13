<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Settings;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Setting extends Model
{
    use HasFactory;
    use LogsActivity;

    protected $fillable = [
        'key',
        'value',
        'group',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'array',
        ];
    }

    /**
     * Secret settings are audited as a change, never as a value.
     *
     * The point of the audit entry is "who changed the newsletter key and when",
     * not what it was set to. Logging the value — even encrypted — puts a secret
     * in a table that is read far more widely than the settings screen.
     */
    public function getActivitylogOptions(): LogOptions
    {
        $attributes = app(Settings::class)->isEncrypted((string) $this->key)
            ? ['key', 'group']
            : ['key', 'value', 'group'];

        return LogOptions::defaults()
            ->logOnly($attributes)
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
