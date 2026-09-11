<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('industries', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();
            $table->string('code')->nullable();
            $table->timestamps();
        });

        Schema::create('industry_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('industry_id')->constrained()->cascadeOnDelete();
            $table->char('locale', 2);
            $table->string('name');

            $table->unique(['industry_id', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('industry_translations');
        Schema::dropIfExists('industries');
    }
};
