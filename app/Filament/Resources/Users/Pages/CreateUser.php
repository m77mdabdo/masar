<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Invitation flow: if no password was set, generate one nobody knows and
        // send a reset link instead. A colleague's password is not ours to pick.
        if (blank($data['password'] ?? null)) {
            $data['password'] = Hash::make(UserResource::randomPassword());
            $this->shouldInvite = true;
        }

        return $data;
    }

    private bool $shouldInvite = false;

    protected function afterCreate(): void
    {
        if (! $this->shouldInvite) {
            return;
        }

        Password::broker()->sendResetLink(['email' => $this->getRecord()->email]);

        Notification::make()
            ->success()
            ->title('أُرسلت دعوة')
            ->body('وصل رابط تعيين كلمة المرور إلى '.$this->getRecord()->email)
            ->send();
    }
}
