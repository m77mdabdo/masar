<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Denormalised publish-gate result.
 *
 * The gate is PHP — it reads config, counts summary points and looks at related
 * sources — so "show me everything blocked" could only be answered by loading
 * candidates and evaluating them. That is fine at 49 articles and wrong at 4,900.
 *
 * NULL means never evaluated (a row that predates this column, or one nothing
 * has touched since). 0 means the gate passes. Anything higher is the number of
 * unmet rules.
 *
 * No index: the column is always filtered alongside `status`, and
 * (status, published_at) already leads every query that uses it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table): void {
            $table->unsignedSmallInteger('gate_failures_count')
                ->nullable()
                ->after('views_count');
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table): void {
            $table->dropColumn('gate_failures_count');
        });
    }
};
