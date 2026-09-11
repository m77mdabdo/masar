<?php

declare(strict_types=1);

use App\Enums\EntityRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entity_mentions', function (Blueprint $table): void {
            $table->string('role', 32)->default(EntityRole::Mentioned->value)->change();

            $table->index('role', 'entity_mentions_role_idx');
        });
    }

    public function down(): void
    {
        Schema::table('entity_mentions', function (Blueprint $table): void {
            $table->dropIndex('entity_mentions_role_idx');

            $table->enum('role', array_column(EntityRole::cases(), 'value'))
                ->default(EntityRole::Mentioned->value)->change();
        });
    }
};
