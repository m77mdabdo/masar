<?php

declare(strict_types=1);

namespace App\Filament\Support;

use Filament\AvatarProviders\Contracts\AvatarProvider;
use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * A staff member's initials, drawn here as an inline SVG.
 *
 * Filament's default provider loads every avatar from ui-avatars.com with the
 * person's name in the URL, so each admin page view sent staff names to a third
 * party — found when the admin audit ran with the network down and every page
 * logged a failed request. Nothing is fetched from outside (owner's ruling); a
 * data URI keeps the avatar without the request.
 */
final class InitialsAvatarProvider implements AvatarProvider
{
    public function get(Model|Authenticatable $record): string
    {
        $initials = collect(preg_split('/\s+/u', trim((string) Filament::getNameForDefaultAvatar($record))) ?: [])
            ->map(fn (string $part): string => mb_substr((string) preg_replace('/^[^\p{L}\p{N}]+/u', '', $part), 0, 1))
            ->filter()
            ->take(2)
            ->implode('');

        $text = htmlspecialchars(mb_strtoupper($initials), ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 64 64">'
            .'<rect width="64" height="64" fill="#001D17"/>'
            .'<text x="32" y="32" dy=".35em" text-anchor="middle" font-family="system-ui, sans-serif" font-size="26" font-weight="600" fill="#F5F2E9">'.$text.'</text>'
            .'</svg>';

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
