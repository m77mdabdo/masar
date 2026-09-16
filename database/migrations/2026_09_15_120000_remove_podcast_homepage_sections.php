<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Removes the podcast section from any homepage layout.
 *
 * There is no podcast. The section was configured to pull `content_type =
 * interview`, so it presented interview articles under a heading that promised
 * audio — a promise to a reader we cannot keep and are not going to keep by
 * recording episodes to fill a template.
 *
 * This runs as a migration rather than living only in the seeder because the
 * `podcast` case is gone from HomepageSectionType, and a surviving row would
 * throw on the enum cast the next time a layout is read. A fresh install and an
 * existing database have to converge.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('homepage_sections')->where('type', 'podcast')->delete();
    }

    public function down(): void
    {
        // Deliberately irreversible. Recreating the section would restore a
        // heading with nothing behind it; if a podcast is ever built, the
        // section is added with it rather than resurrected empty.
    }
};
