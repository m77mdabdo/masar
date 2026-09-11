<?php

declare(strict_types=1);

use App\Enums\CompanyType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * No standalone index on `type`: the existing composite (type, industry_id)
 * already covers every query a single-column index on `type` could serve.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->string('type', 32)->default(CompanyType::Company->value)->change();
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->enum('type', array_column(CompanyType::cases(), 'value'))
                ->default(CompanyType::Company->value)->change();
        });
    }
};
