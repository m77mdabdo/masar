<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The return-audience mechanism.
 *
 * `visitor_id` is a first-party anonymous identifier. It is how we measure
 * returning audience — the primary KPI — before reader accounts exist. It is
 * deliberately not linked to any third-party identity graph.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscribers', function (Blueprint $table): void {
            $table->id();
            $table->string('email')->unique();
            $table->char('locale', 2)->default('ar');
            $table->string('status')->default('pending');
            $table->timestamp('verified_at')->nullable();
            $table->json('preferences')->nullable();
            $table->string('visitor_id')->nullable()->index();
            $table->string('source')->nullable();
            $table->timestamp('unsubscribed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscribers');
    }
};
