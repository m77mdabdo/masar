<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('redirects', function (Blueprint $table): void {
            $table->timestamp('last_hit_at')->nullable()->after('hits');
        });
    }

    public function down(): void
    {
        Schema::table('redirects', function (Blueprint $table): void {
            $table->dropColumn('last_hit_at');
        });
    }
};
