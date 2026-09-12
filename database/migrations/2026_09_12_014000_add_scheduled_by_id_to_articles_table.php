<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who scheduled the publication.
 *
 * Scheduling *is* the editorial decision; the cron only carries it out. Without
 * this column the scheduled publish had to infer an actor from editor_id, which
 * credits the audit trail to someone who may not have made the call — worse than
 * an incomplete trail, because it looks complete and is wrong.
 *
 * Nullable: rows scheduled before this column existed fall back to inference.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table): void {
            $table->foreignId('scheduled_by_id')
                ->nullable()
                ->after('fact_checker_id')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('scheduled_by_id');
        });
    }
};
