<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An author portrait.
 *
 * The byline monogram stays as the fallback — a stock silhouette is a request
 * that tells the reader nothing — and a real photograph wins when one exists.
 * The prototype is explicit about the rule this column has to respect:
 * portraits must be real photographs of real people, never generated.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('photo_path')->nullable()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('photo_path');
        });
    }
};
