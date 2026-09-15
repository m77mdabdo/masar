<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marks an image that is not a photograph of the thing it appears to show.
 *
 * A generated skyline can carry mood behind a headline. It cannot illustrate a
 * story, a company or a place, because a reader has no way to tell the
 * difference and every other image on the site is a real photograph of a real
 * location — which is exactly what makes this one readable as reportage.
 *
 * A column rather than a custom property so the restriction can be enforced in
 * a query: the article hero picker filters on it, and a model guard rejects it.
 * A rule that lives only in a document is a rule the next person breaks.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media', function (Blueprint $table): void {
            $table->boolean('is_illustrative')->default(false)->after('collection_name');
        });
    }

    public function down(): void
    {
        Schema::table('media', function (Blueprint $table): void {
            $table->dropColumn('is_illustrative');
        });
    }
};
