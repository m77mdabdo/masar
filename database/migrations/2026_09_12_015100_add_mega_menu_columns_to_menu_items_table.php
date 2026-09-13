<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mega-menu support on top-level navigation items.
 *
 * `is_mega` turns a top-level item into a panel; `column_group` lets its
 * children be arranged into named columns inside that panel. Both are ignored
 * for items that are not top-level, which is enforced in the navigation page
 * rather than the schema — a nested item that once had a group should keep the
 * value if it is ever promoted back.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menu_items', function (Blueprint $table): void {
            $table->boolean('is_mega')->default(false)->after('show_mobile');
            $table->string('column_group')->nullable()->after('is_mega');
        });
    }

    public function down(): void
    {
        Schema::table('menu_items', function (Blueprint $table): void {
            $table->dropColumn(['is_mega', 'column_group']);
        });
    }
};
