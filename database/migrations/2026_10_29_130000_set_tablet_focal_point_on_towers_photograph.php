<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * The towers photograph's centre for the 768–1023 stage. Measured from the
 * pixels: the right-hand tower's body ends at 87.8% of the frame. At 768 the
 * visible right edge at the drift's 1.04 start is 0.667 + 0.333·x, so the
 * tower is whole from x ≥ 64.9%; 65% it is. Phones keep `focal` (56%), where
 * 65% would crop out the bicycles. down() removes only the value set here.
 */
return new class extends Migration
{
    private const FILE = 'pexels-aburhman187-29859597.jpg';

    private const FOCAL_MD = '65% 40%';

    public function up(): void
    {
        DB::table('media')->where('custom_properties->source_file', self::FILE)
            ->update(['custom_properties' => DB::raw("JSON_SET(custom_properties, '$.focal_md', ".DB::getPdo()->quote(self::FOCAL_MD).')')]);
    }

    public function down(): void
    {
        DB::table('media')->where('custom_properties->source_file', self::FILE)
            ->where('custom_properties->focal_md', self::FOCAL_MD)
            ->update(['custom_properties' => DB::raw("JSON_REMOVE(custom_properties, '$.focal_md')")]);
    }
};
