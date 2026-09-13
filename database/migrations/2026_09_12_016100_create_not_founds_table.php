<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aggregated 404 log.
 *
 * One row per path, not per hit: a scanner sweeping a thousand URLs would
 * otherwise write a thousand rows a minute and bury the handful of dead links
 * that are actually costing us readers.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('not_founds', function (Blueprint $table): void {
            $table->id();
            $table->string('path')->unique();
            $table->string('referrer')->nullable();
            $table->unsignedBigInteger('hits')->default(1);
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->boolean('resolved')->default(false);
            $table->timestamps();

            // The listing is "unresolved, worst first".
            $table->index(['resolved', 'hits']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('not_founds');
    }
};
