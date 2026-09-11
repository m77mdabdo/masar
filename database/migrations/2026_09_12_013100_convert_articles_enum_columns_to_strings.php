<?php

declare(strict_types=1);

use App\Enums\ArticleStatus;
use App\Enums\ContentType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Native MySQL ENUM → string(32).
 *
 * Adding a 15th workflow status to an ENUM column is an ALTER that rewrites the
 * whole table. The editorial workflow will grow, and a schema that makes growth
 * expensive is a schema that quietly discourages it. The PHP enum still guards
 * every write through the model cast, so behaviour is unchanged — only storage.
 *
 * No standalone index is added for `status`: it already carries one, and the
 * composite (status, published_at) covers it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table): void {
            $table->string('status', 32)->default(ArticleStatus::Idea->value)->change();
            $table->string('content_type', 32)->default(ContentType::News->value)->change();

            $table->index('content_type');
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table): void {
            $table->dropIndex(['content_type']);

            $table->enum('status', array_column(ArticleStatus::cases(), 'value'))
                ->default(ArticleStatus::Idea->value)->change();
            $table->enum('content_type', array_column(ContentType::cases(), 'value'))
                ->default(ContentType::News->value)->change();
        });
    }
};
