<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The advertising mechanism. Deliberately not a marketplace.
 *
 * Placements, a campaign with dates, a creative, and a count. No targeting, no
 * bidding, no reporting beyond impressions and clicks. There are no advertisers
 * and there will not be before handover; what has to exist is the proof that
 * paid placement is unambiguously marked, because that is the editorial
 * commitment rather than the revenue feature.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_placements', function (Blueprint $table): void {
            // A slot in the design, named by the template that renders it.
            $table->id();
            $table->string('key', 64)->unique();
            $table->string('name');
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('ad_campaigns', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            // Who is paying. Rendered to the reader, not just stored.
            $table->string('advertiser');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamps();

            $table->index(['is_active', 'starts_at', 'ends_at']);
        });

        Schema::create('ad_creatives', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ad_campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ad_placement_id')->constrained()->cascadeOnDelete();

            $table->string('image_path');
            // Required, not nullable: an advertisement is an image, and an image
            // without alt text is invisible to a reader using a screen reader
            // while still being paid for.
            $table->string('alt');
            $table->string('destination_url');

            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('impressions')->default(0);
            $table->unsignedInteger('clicks')->default(0);
            $table->timestamps();

            $table->index(['ad_placement_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_creatives');
        Schema::dropIfExists('ad_campaigns');
        Schema::dropIfExists('ad_placements');
    }
};
