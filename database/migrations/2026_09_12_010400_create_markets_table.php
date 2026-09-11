<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('markets', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();
            $table->string('code')->nullable(); // e.g. TASI, NOMU
            $table->timestamps();
        });

        Schema::create('market_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('market_id')->constrained()->cascadeOnDelete();
            $table->char('locale', 2);
            $table->string('name');

            $table->unique(['market_id', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('market_translations');
        Schema::dropIfExists('markets');
    }
};
