<?php

declare(strict_types=1);

use App\Enums\EntityRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The most important table in the schema.
 *
 * Both sides are polymorphic: any content type (article, report, video,
 * opportunity) mentions any entity type (company, person, country, industry,
 * market). This is what lets /ar/companies/aramco aggregate every piece of
 * content referencing Aramco in one query instead of a union per content type.
 *
 * This table grows faster than everything else combined, so:
 *  - the morph type columns are short strings backed by a morph map
 *    (`company`, not `App\Models\Company`), registered in AppServiceProvider;
 *  - (entity_type, entity_id, created_at) is the index that makes entity
 *    profile pages viable — it serves both the filter and the sort;
 *  - there is no updated_at. A mention is created or deleted, never edited.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entity_mentions', function (Blueprint $table): void {
            $table->id();

            $table->string('mentionable_type', 100);
            $table->unsignedBigInteger('mentionable_id');

            $table->string('entity_type', 100);
            $table->unsignedBigInteger('entity_id');

            $table->enum('role', array_column(EntityRole::cases(), 'value'))
                ->default(EntityRole::Mentioned->value);

            // 0-100: how central this entity is to the piece.
            $table->unsignedTinyInteger('prominence')->default(0);

            $table->timestamp('created_at')->nullable();

            $table->index(['entity_type', 'entity_id', 'created_at'], 'entity_mentions_entity_idx');
            $table->index(['mentionable_type', 'mentionable_id'], 'entity_mentions_mentionable_idx');
            $table->unique(
                ['mentionable_type', 'mentionable_id', 'entity_type', 'entity_id'],
                'entity_mentions_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entity_mentions');
    }
};
