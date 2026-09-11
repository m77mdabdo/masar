<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `articles_status_index` on (status) is a left prefix of
 * `articles_status_published_at_index` on (status, published_at), which MySQL
 * will use for any query the single-column index could serve.
 *
 * Same redundancy as the one removed from entity_mentions: pure write cost on
 * every insert and every status transition, for no read benefit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table): void {
            $table->dropIndex('articles_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table): void {
            $table->index('status', 'articles_status_index');
        });
    }
};
