<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which of the four questions a body block answers.
 *
 * The article page renders the four questions as the article's actual
 * structure — icon, kicker, subhead, then that question's blocks — rather than
 * as a summary band sitting above a second copy of the same material.
 *
 * NULL is meaningful: it means the block belongs to no question and renders in
 * a general stream. An opinion piece is not four questions, and a schema that
 * cannot say so would force every article into a shape that only fits some.
 *
 * `string(32)`, not a native ENUM — CLAUDE.md §6. No index: blocks are always
 * loaded for one article through the relation and grouped in memory, so the
 * existing `(article_id, sort_order)` already covers every query that reads
 * them, and a second index here would be pure write cost.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('article_blocks', function (Blueprint $table): void {
            $table->string('section', 32)->nullable()->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('article_blocks', function (Blueprint $table): void {
            $table->dropColumn('section');
        });
    }
};
