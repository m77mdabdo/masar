<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Throttles the confirmation mail per address.
 *
 * Double opt-in hands anyone who can reach the form a way to send mail to an
 * address they do not own: submit a victim's address repeatedly and each
 * submission is another message. A per-IP rate limit does not close it, because
 * the cost lands on the address, not the sender. This column is what lets the
 * action refuse to send again inside the cooling-off window while still showing
 * the submitter the same neutral response as every other outcome.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscribers', function (Blueprint $table): void {
            $table->timestamp('confirmation_sent_at')->nullable()->after('verified_at');
        });
    }

    public function down(): void
    {
        Schema::table('subscribers', function (Blueprint $table): void {
            $table->dropColumn('confirmation_sent_at');
        });
    }
};
