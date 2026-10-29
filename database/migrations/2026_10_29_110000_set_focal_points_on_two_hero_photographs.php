<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * Per-image focal points for the two article photographs whose subjects the
 * big story's crop cut (Task 2.11): the right-hand tower at 768, and the
 * notebook on a phone. Stored as the media records' `focal` custom property,
 * which the big story and the article hero read; the default stays 50% 40%.
 *
 * Every media row made from each source file gets it (the library keeps one
 * row per article). down() removes the value only where it is still the one
 * set here, so an editor's later change survives a rollback.
 */
return new class extends Migration
{
    private const FOCAL = [
        'pexels-aburhman187-29859597.jpg' => '56% 40%',
        'pexels-tima-miroshnichenko-5717315.jpg' => '40% 40%',
    ];

    public function up(): void
    {
        foreach (self::FOCAL as $file => $focal) {
            DB::table('media')
                ->where('custom_properties->source_file', $file)
                ->update(['custom_properties' => DB::raw("JSON_SET(custom_properties, '$.focal', ".DB::getPdo()->quote($focal).')')]);
        }
    }

    public function down(): void
    {
        foreach (self::FOCAL as $file => $focal) {
            DB::table('media')
                ->where('custom_properties->source_file', $file)
                ->where('custom_properties->focal', $focal)
                ->update(['custom_properties' => DB::raw("JSON_REMOVE(custom_properties, '$.focal')")]);
        }
    }
};
