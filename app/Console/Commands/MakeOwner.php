<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Laravel\Prompts\password as promptPassword;

/**
 * Gives someone the keys to the admin panel.
 *
 * A fresh `migrate:fresh --seed` leaves four editorial accounts and nobody who
 * can reach the control layer: the homepage composer, settings, navigation and
 * the intelligence inbox all sit behind permissions no seeded role holds. TASK
 * 04's premise is that the owner changes the site without a developer, so the
 * owner has to be able to get in without one either.
 *
 * The password is prompted for, never passed as an argument: an argument lands
 * in the shell history and in the process list, where it outlives the session.
 */
class MakeOwner extends Command
{
    protected $signature = 'masar:owner
        {email : The account to create or promote}
        {--name= : Display name, asked for if omitted}
        {--replace= : An existing account to remove once this one works}';

    protected $description = 'Create or promote an account to super_admin for the admin panel';

    public function handle(): int
    {
        $email = (string) $this->argument('email');

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error("Not an email address: {$email}");

            return self::FAILURE;
        }

        if (! Role::query()->where('name', 'super_admin')->exists()) {
            $this->error('The super_admin role does not exist. Run `php artisan db:seed --class=RoleSeeder` first.');

            return self::FAILURE;
        }

        $user = User::query()->firstWhere('email', $email);
        $existing = $user !== null;

        $name = (string) ($this->option('name')
            ?: $user?->name
            ?: $this->ask('Display name'));

        if (trim($name) === '') {
            $this->error('A display name is required.');

            return self::FAILURE;
        }

        $secret = promptPassword(
            label: $existing ? 'New password (blank to keep the current one)' : 'Password',
            required: ! $existing,
        );

        if ($secret !== '' && mb_strlen($secret) < 12) {
            $this->error('Use at least 12 characters: this account can publish.');

            return self::FAILURE;
        }

        $user ??= new User;
        $user->name = $name;
        $user->email = $email;
        $user->email_verified_at ??= now();

        if ($secret !== '') {
            // Cast to `hashed` on the model, so this is never stored in the clear.
            $user->password = $secret;
        }

        $user->save();
        $user->syncRoles(['super_admin']);

        $replace = (string) ($this->option('replace') ?? '');

        if ($replace !== '' && $replace !== $email) {
            $removed = User::query()->where('email', $replace)->delete();
            $this->line("Removed {$removed} account(s) matching {$replace}.");
        }

        $user = $user->fresh();

        $this->newLine();
        $this->info($existing ? "Promoted {$email}." : "Created {$email}.");
        $this->table(['', ''], [
            ['name', $user->name],
            ['role', $user->getRoleNames()->implode(', ')],
            ['permissions', $user->getAllPermissions()->count().' of '.Permission::count()],
            ['panel', rtrim((string) config('app.url'), '/').'/admin'],
        ]);

        // Not a warning about the command — a warning about the next screen.
        $this->warn('This account can publish, so the panel will require two-factor');
        $this->warn('authentication on first sign-in. Keep the recovery codes it gives you.');

        return self::SUCCESS;
    }
}
