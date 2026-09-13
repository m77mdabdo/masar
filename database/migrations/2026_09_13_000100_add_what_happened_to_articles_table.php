<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The fourth column of the editorial spine.
 *
 * The article page presents four icon-led sections — what happened, why it
 * matters, who is affected, where the opportunity is — and three of them had
 * fields. The fourth was being read out of the body, which meant the band's
 * shape depended on whether a writer happened to use a particular heading.
 *
 * Nullable, and not added to the publish gate: the gate's list is a product
 * decision, and this ships as an optional field until someone makes it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table): void {
            $table->text('what_happened')->nullable()->after('body');
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table): void {
            $table->dropColumn('what_happened');
        });
    }
};
