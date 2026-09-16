<?php

declare(strict_types=1);

use App\Models\AdCampaign;
use App\Models\AdCreative;
use App\Models\AdPlacement;
use App\Support\AdServer;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Redis;

/**
 * The advertising mechanism — placements, a campaign with dates, a creative, a
 * count. Not a marketplace: no targeting, no bidding, no dashboard.
 *
 * What is tested hardest is not the serving. It is that an advertisement is
 * marked as one, that an expired campaign stops, and that nothing about an ad
 * can break the page it sits on.
 */
function servableCreative(array $campaign = [], array $creative = []): AdCreative
{
    $placement = AdPlacement::factory()->create(['key' => 'test_slot']);

    return AdCreative::factory()->create(array_merge([
        'ad_placement_id' => $placement->getKey(),
        'ad_campaign_id' => AdCampaign::factory()->create($campaign)->getKey(),
    ], $creative));
}

it('serves a creative whose campaign is running', function (): void {
    servableCreative();

    expect(app(AdServer::class)->forPlacement('test_slot'))->not->toBeNull();
});

it('serves nothing once the campaign has ended', function (): void {
    servableCreative(['starts_at' => now()->subMonths(2), 'ends_at' => now()->subDay()]);

    expect(app(AdServer::class)->forPlacement('test_slot'))->toBeNull();
});

it('serves nothing before the campaign starts', function (): void {
    servableCreative(['starts_at' => now()->addWeek(), 'ends_at' => now()->addMonth()]);

    expect(app(AdServer::class)->forPlacement('test_slot'))->toBeNull();
});

it('serves nothing while the campaign is paused', function (): void {
    servableCreative(['is_active' => false]);

    expect(app(AdServer::class)->forPlacement('test_slot'))->toBeNull();
});

it('marks an advertisement as one, and names who paid', function (): void {
    servableCreative(['advertiser' => 'شركة المعلن']);

    $html = Blade::render('<x-ads.slot placement="test_slot" />');

    expect($html)->toContain('إعلان')
        ->and($html)->toContain('شركة المعلن')
        // Paid links must not pass ranking signals.
        ->and($html)->toContain('rel="nofollow sponsored noopener"');
});

it('renders nothing at all when no campaign is running', function (): void {
    // An empty bordered box labelled "advertisement" is a worse page than no box.
    servableCreative(['is_active' => false]);

    expect(trim(Blade::render('<x-ads.slot placement="test_slot" />')))->toBe('');
});

it('never renders an advertisement without alt text', function (): void {
    // An image an advertiser paid for that a screen-reader user cannot perceive
    // is a slot billed and not delivered. `alt` is NOT NULL in the schema; this
    // asserts the rendered output too.
    servableCreative(creative: ['alt' => 'حملة الخدمات اللوجستية']);

    expect(Blade::render('<x-ads.slot placement="test_slot" />'))->toContain('alt="حملة الخدمات اللوجستية"');
});

it('buffers impressions in redis rather than writing on every request', function (): void {
    // §5: never increment() on every request. An ad slot is on more pages than
    // an article is.
    $creative = servableCreative();
    Redis::connection()->del('ads:impressions');

    app(AdServer::class)->recordImpression($creative);
    app(AdServer::class)->recordImpression($creative);

    expect((int) $creative->fresh()->impressions)->toBe(0)
        ->and((int) Redis::connection()->hget('ads:impressions', (string) $creative->getKey()))->toBe(2);

    app(AdServer::class)->flushImpressions();

    expect((int) $creative->fresh()->impressions)->toBe(2)
        ->and((int) Redis::connection()->hget('ads:impressions', (string) $creative->getKey()))->toBe(0);
});

it('counts a click and forwards to the advertiser', function (): void {
    $creative = servableCreative(creative: ['destination_url' => 'https://advertiser.test/offer']);

    $this->get(route('web.ads.click', ['locale' => 'ar', 'creative' => $creative->getKey()]))
        ->assertRedirect('https://advertiser.test/offer');

    expect((int) $creative->fresh()->clicks)->toBe(1);
});

it('kills the click-through when the campaign has ended', function (): void {
    // An expired campaign's link is dead, not merely uncounted.
    $creative = servableCreative(['ends_at' => now()->subDay(), 'starts_at' => now()->subMonth()]);

    $this->get(route('web.ads.click', ['locale' => 'ar', 'creative' => $creative->getKey()]))
        ->assertNotFound();

    expect((int) $creative->fresh()->clicks)->toBe(0);
});
