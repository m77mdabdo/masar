<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * A byline in each edition. The demo staff are named by role («رئيس التحرير»),
 * which is a phrase to translate, not a name; a real writer's name is
 * transliterated per edition. NULL, or a missing edition, shows `name`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->json('name_translations')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('name_translations');
        });
    }
};
