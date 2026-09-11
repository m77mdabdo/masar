<?php

declare(strict_types=1);

use App\Enums\OpportunityPotential;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * No standalone index on `potential`: the existing composite
 * (potential, status, published_at) already covers it as a left prefix.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('opportunities', function (Blueprint $table): void {
            $table->string('potential', 32)->default(OpportunityPotential::Medium->value)->change();
        });
    }

    public function down(): void
    {
        Schema::table('opportunities', function (Blueprint $table): void {
            $table->enum('potential', array_column(OpportunityPotential::cases(), 'value'))
                ->default(OpportunityPotential::Medium->value)->change();
        });
    }
};
