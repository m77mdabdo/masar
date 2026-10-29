<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Owner approval to publish a translation file is not a linguistic review
 * (owner's directive, 2026-10-29). The review columns say whether a speaker
 * of the language read the words; these say who authorised publishing them,
 * on what basis, and for which content hash. A file that changes loses both.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('translation_reviews', function (Blueprint $table): void {
            $table->string('publication_status', 32)->default('unapproved')->after('status');
            $table->string('publication_approved_by', 191)->nullable()->after('publication_status');
            $table->string('publication_basis', 500)->nullable()->after('publication_approved_by');
            $table->char('publication_hash', 64)->nullable()->after('publication_basis');
            $table->timestamp('publication_approved_at')->nullable()->after('publication_hash');
        });
    }

    public function down(): void
    {
        Schema::table('translation_reviews', function (Blueprint $table): void {
            $table->dropColumn(['publication_status', 'publication_approved_by', 'publication_basis', 'publication_hash', 'publication_approved_at']);
        });
    }
};
