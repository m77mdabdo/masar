<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `entity_mentions_mentionable_idx` on (mentionable_type, mentionable_id) is a
 * left prefix of `entity_mentions_unique` on
 * (mentionable_type, mentionable_id, entity_type, entity_id).
 *
 * MySQL will use the unique index for any query the prefix index could serve,
 * so the prefix index buys nothing on reads and costs a second B-tree write on
 * every insert — on the table that grows faster than all the others combined.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entity_mentions', function (Blueprint $table): void {
            $table->dropIndex('entity_mentions_mentionable_idx');
        });
    }

    public function down(): void
    {
        Schema::table('entity_mentions', function (Blueprint $table): void {
            $table->index(['mentionable_type', 'mentionable_id'], 'entity_mentions_mentionable_idx');
        });
    }
};
