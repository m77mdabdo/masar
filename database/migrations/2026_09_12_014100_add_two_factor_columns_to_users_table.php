<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TOTP enrolment for the admin panel.
 *
 * Both columns hold secrets and are encrypted at the model layer, so they are
 * text rather than a fixed-width string: ciphertext is much longer than the
 * 32-character secret it protects.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->text('app_authentication_secret')->nullable()->after('password');
            $table->text('app_authentication_recovery_codes')->nullable()->after('app_authentication_secret');
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('app_authentication_recovery_codes');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'app_authentication_secret',
                'app_authentication_recovery_codes',
                'two_factor_confirmed_at',
            ]);
        });
    }
};
