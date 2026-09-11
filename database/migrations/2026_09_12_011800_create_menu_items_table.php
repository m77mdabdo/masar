<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `linkable_*` points a menu item at a real entity so the URL follows the entity
 * when its slug changes. Prefer it over a hand-typed `url`, which silently rots.
 *
 * `starts_at` / `ends_at` let editorial schedule a campaign item without anyone
 * having to remember to remove it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('menu_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('menu_items')->cascadeOnDelete();

            // Translated in place: {"ar": "...", "en": "..."}
            $table->json('label');

            $table->string('url')->nullable();
            $table->string('linkable_type', 100)->nullable();
            $table->unsignedBigInteger('linkable_id')->nullable();

            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('show_desktop')->default(true);
            $table->boolean('show_mobile')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();

            $table->index(['menu_id', 'parent_id', 'sort_order']);
            $table->index(['linkable_type', 'linkable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_items');
    }
};
