<?php

declare(strict_types=1);

use App\Enums\CompanyType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Companies, funds, government bodies and startups all live here.
 *
 * A startup is `type = startup`, never a separate table: it graduates into a
 * company without a migration, and every historical mention survives the change.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();
            $table->enum('type', array_column(CompanyType::cases(), 'value'))
                ->default(CompanyType::Company->value);
            $table->foreignId('industry_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('country_id')->nullable()->constrained()->nullOnDelete();
            $table->smallInteger('founded_year')->nullable();
            $table->string('website')->nullable();
            $table->string('ticker')->nullable();
            $table->string('logo_path')->nullable();
            $table->boolean('is_verified')->default(false);
            $table->integer('mentions_count')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['type', 'industry_id']);
            $table->index('country_id');
        });

        Schema::create('company_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->char('locale', 2);
            $table->string('name');
            $table->string('short_description')->nullable();
            $table->text('description')->nullable();

            $table->unique(['company_id', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_translations');
        Schema::dropIfExists('companies');
    }
};
