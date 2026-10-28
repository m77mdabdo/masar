<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * char(2) held `ar` and `en`. It cannot hold `ur-pk` or `zh-hans`, the
 * URL segments of two of the six editions (config('masar.locales')). Ten
 * leaves room for a region subtag on any later edition.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('market_translations', function (Blueprint $table): void {
            $table->string('locale', 10)->change();
        });
    }

    public function down(): void
    {
        // Narrowing would truncate `ur-pk` to `ur` silently — a row that then
        // claims to be in a locale that does not exist. Refuse instead.
        if (DB::table('market_translations')->whereRaw('CHAR_LENGTH(locale) > 2')->exists()) {
            throw new RuntimeException('market_translations holds locales longer than two characters; remove them before rolling back.');
        }

        Schema::table('market_translations', function (Blueprint $table): void {
            $table->char('locale', 2)->change();
        });
    }
};
