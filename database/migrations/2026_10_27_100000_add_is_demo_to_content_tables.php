<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marks every row demo seeding creates, so `masar:purge-demo` can remove all of
 * it before this database becomes production.
 *
 * One migration for every table, against the one-table-per-file convention
 * (CLAUDE.md §6), on the owner's explicit instruction: the flag is one decision
 * applied everywhere, and splitting it would let a table be missed.
 *
 * Translation tables and pivots carry no flag: each cascades from a parent
 * that does. Taxonomy (categories, countries, industries, markets) and site
 * structure (menus, the homepage layout) carry none either — real content
 * needs them, so they are not demo.
 *
 * No index: the flag is read by a purge that runs once, and an index here is
 * write cost on every insert for a query that is not on any request path.
 */
return new class extends Migration
{
    /** @var array<int, string> */
    private const TABLES = [
        'articles',
        'article_blocks',
        'article_sources',
        'entity_mentions',
        'companies',
        'people',
        'topics',
        'opportunities',
        'media',
        'users',
        'subscribers',
        'sources',
        'source_items',
        'intelligence_items',
        'settings',
        'activity_log',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->boolean('is_demo')->default(false);
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dropColumn('is_demo');
            });
        }
    }
};
