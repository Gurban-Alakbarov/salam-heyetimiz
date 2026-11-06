<?php

use App\Domain\Site\Models\SiteDocument;
use App\Domain\Site\Models\SiteRelease;
use App\Domain\Site\Models\SiteRevision;
use App\Domain\Site\Publishing\SiteLivePointer;
use App\Domain\Site\Publishing\SitePublisher;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

/** The real pointer is a symlink; here it is a variable, so the tests also run on Windows. */
class FakeSitePointer extends SiteLivePointer
{
    public ?string $live = null;

    public int $moves = 0;

    public function __construct() {}

    public function current(): ?string
    {
        return $this->live;
    }

    public function point(string $directory): void
    {
        $this->live = $directory;
        $this->moves++;
    }
}

beforeEach(function () {
    seedRbac();
    $this->artisan('site:import')->assertSuccessful();
    $this->admin = makeSuperAdmin();

    $this->root = sys_get_temp_dir().'/site-publish-test-'.uniqid();
    $this->pointer = new FakeSitePointer;
    app()->instance(SiteLivePointer::class, $this->pointer);
    app()->instance(SitePublisher::class, new SitePublisher($this->pointer, $this->root, base_path('site')));
});

afterEach(fn () => File::deleteDirectory($this->root));

function livePage(object $test, string $file): string
{
    return file_get_contents($test->root.'/'.$test->pointer->live.'/'.$file);
}

function editTitle(object $test, string $text, string $key = 'services'): array
{
    $doc = $test->actingAs($test->admin, 'admin')->getJson("/admin/v1/site/documents/{$key}")->json('data');
    $content = $doc['content'];
    $content['sections'][0]['title']['az'] = $text;

    return $test->actingAs($test->admin, 'admin')
        ->putJson("/admin/v1/site/documents/{$key}", ['content' => $content, 'version' => $doc['version']])
        ->assertOk()->json('data');
}

function publishSite(object $test)
{
    return $test->actingAs($test->admin, 'admin')->postJson('/admin/v1/site/publish');
}

it('makes the first live copy from what is already published, drafts aside', function () {
    editTitle($this, 'Hələ qaralama');

    $this->artisan('site:rebuild')->assertSuccessful();

    expect($this->pointer->live)->not->toBeNull();
    expect(livePage($this, 'services.html'))->not->toContain('Hələ qaralama');
    expect(SiteDocument::query()->where('key', 'services')->first()->hasUnpublishedChanges())->toBeTrue();
    expect(SiteRelease::query()->first()->kind)->toBe('rebuild');
});

it('publishes saved changes, audited, and clears the waiting state', function () {
    editTitle($this, 'Yeni başlıq');

    $status = publishSite($this)->assertOk()->json('data');

    expect(livePage($this, 'services.html'))->toContain('Yeni başlıq');
    expect($status['changed'])->toBe([]);
    expect($status['releases'][0]['live'])->toBeTrue();
    expect($status['releases'][0]['pages'])->toBe(30);
    expect($status['releases'][0]['admin'])->toBe($this->admin->name);
    $this->assertDatabaseHas('audit_logs', ['action' => 'site.published']);
});

it('says what would go out, with translation warnings', function () {
    editTitle($this, 'Yeni başlıq');

    $status = $this->actingAs($this->admin, 'admin')->getJson('/admin/v1/site/publish')->assertOk()->json('data');

    expect($status['changed'])->toHaveCount(1);
    expect($status['changed'][0]['key'])->toBe('services');
    expect($status['translations_stale'])->toBe(2);
    expect($status['unfinished'])->toBe([]);
});

it('does not publish when nothing changed', function () {
    publishSite($this)->assertStatus(409)->assertJsonPath('error.code', 'site_nothing_to_publish');

    expect($this->pointer->moves)->toBe(0);
});

it('refuses a page with no name, and changes nothing live', function () {
    $doc = $this->actingAs($this->admin, 'admin')->getJson('/admin/v1/site/documents/faq')->json('data');
    $content = $doc['content'];
    $content['title']['az'] = '';
    $this->actingAs($this->admin, 'admin')->putJson('/admin/v1/site/documents/faq', ['content' => $content, 'version' => 1])->assertOk();

    publishSite($this)->assertStatus(422)
        ->assertJsonPath('error.code', 'site_publish_unfinished')
        ->assertJsonPath('error.details.pages', ['faq']);

    expect($this->pointer->moves)->toBe(0);
});

it('lets only one publish run at a time', function () {
    editTitle($this, 'Yeni');
    $lock = Cache::lock('site-publish', 60);
    $lock->get();

    publishSite($this)->assertStatus(409)->assertJsonPath('error.code', 'site_publish_busy');

    $lock->release();
});

it('leaves the live site alone when a build fails', function () {
    publishSite($this);
    editTitle($this, 'Birinci');
    publishSite($this)->assertOk();
    $live = $this->pointer->live;
    $folders = count(File::directories($this->root));

    editTitle($this, 'İkinci');
    SiteDocument::query()->where('key', 'services')->first()->update(['content' => ['key' => 'services', 'title' => ['az' => 'x'], 'sections' => [['type' => 'nope']]]]);

    $this->withoutExceptionHandling();
    expect(fn () => $this->actingAs($this->admin, 'admin')->postJson('/admin/v1/site/publish'))->toThrow(RuntimeException::class);

    expect($this->pointer->live)->toBe($live);
    expect(File::directories($this->root))->toHaveCount($folders);
    expect(SiteRelease::query()->count())->toBe(1);
});

it('goes back to an earlier copy in one step, keeping the drafts', function () {
    editTitle($this, 'Birinci');
    publishSite($this)->assertOk();
    $first = SiteRelease::query()->latest('id')->first();

    editTitle($this, 'İkinci');
    publishSite($this)->assertOk();
    expect(livePage($this, 'services.html'))->toContain('İkinci');

    $status = $this->actingAs($this->admin, 'admin')
        ->postJson("/admin/v1/site/releases/{$first->id}/rollback")->assertOk()->json('data');

    expect(livePage($this, 'services.html'))->toContain('Birinci');
    expect(collect($status['releases'])->firstWhere('live', true)['id'])->toBe($first->id);
    expect($status['changed'][0]['key'])->toBe('services');
    expect(SiteDocument::query()->where('key', 'services')->first()->content['sections'][0]['title']['az'])->toBe('İkinci');
    $this->assertDatabaseHas('audit_logs', ['action' => 'site.rolled_back']);
});

it('rebuilds what was rolled back to, not the newer drafts', function () {
    editTitle($this, 'Birinci');
    publishSite($this)->assertOk();
    $first = SiteRelease::query()->latest('id')->first();
    editTitle($this, 'İkinci');
    publishSite($this)->assertOk();
    $this->actingAs($this->admin, 'admin')->postJson("/admin/v1/site/releases/{$first->id}/rollback")->assertOk();

    $this->artisan('site:rebuild')->assertSuccessful();

    expect(livePage($this, 'services.html'))->toContain('Birinci');
});

it('says so when a copy is no longer on disk', function () {
    editTitle($this, 'Birinci');
    publishSite($this)->assertOk();
    $release = SiteRelease::query()->first();
    File::deleteDirectory($this->root.'/'.$release->directory);

    $this->actingAs($this->admin, 'admin')->postJson("/admin/v1/site/releases/{$release->id}/rollback")
        ->assertStatus(409)->assertJsonPath('error.code', 'site_release_gone');
});

it('keeps ten copies and the live one', function () {
    foreach (range(1, 12) as $i) {
        editTitle($this, "Başlıq {$i}");
        publishSite($this)->assertOk();
    }

    $old = SiteRelease::query()->orderByDesc('id')->get()->last();
    expect(SiteRelease::query()->count())->toBe(10);
    expect(File::directories($this->root))->toHaveCount(10);

    // Rolled back to the oldest, it survives newer publishes while it is live.
    $this->actingAs($this->admin, 'admin')->postJson("/admin/v1/site/releases/{$old->id}/rollback")->assertOk();
    $this->artisan('site:rebuild')->assertSuccessful();
    expect(livePage($this, 'services.html'))->toContain('Başlıq 3');
});

it('keeps the versions an earlier copy needs when old ones are cleaned up', function () {
    editTitle($this, 'Saxlanmalı');
    publishSite($this)->assertOk();
    $kept = SiteRelease::query()->first()->versions['services'];

    foreach (range(1, 55) as $i) {
        editTitle($this, "Qaralama {$i}");
    }

    $document = SiteDocument::query()->where('key', 'services')->first();
    expect(SiteRevision::query()->where('site_document_id', $document->id)->where('version', $kept)->exists())->toBeTrue();
});

it('does not make a new copy when a rebuild changes nothing', function () {
    $this->artisan('site:rebuild')->assertSuccessful();
    $live = $this->pointer->live;

    $this->artisan('site:rebuild')->assertSuccessful();

    expect($this->pointer->live)->toBe($live);
    expect(SiteRelease::query()->count())->toBe(1);
    expect(File::directories($this->root))->toHaveCount(1);
});

it('takes a campaign down after its last day with no one editing', function () {
    Carbon::setTestNow('2026-11-10 12:00:00');
    $doc = $this->actingAs($this->admin, 'admin')->getJson('/admin/v1/site/documents/campaigns')->json('data');
    $content = $doc['content'];
    $content['sections'][1]['items'] = [['title' => ['az' => 'Endirim'], 'image' => '/landing/img-03.webp', 'ends_at' => '2026-11-10']];
    $this->actingAs($this->admin, 'admin')->putJson('/admin/v1/site/documents/campaigns', ['content' => $content, 'version' => 1])->assertOk();
    publishSite($this)->assertOk();

    expect(File::exists($this->root.'/'.$this->pointer->live.'/campaigns.html'))->toBeTrue();
    expect(livePage($this, 'index.html'))->toContain('href="/campaigns"');

    Carbon::setTestNow('2026-11-11 00:10:00');
    $this->artisan('site:rebuild')->assertSuccessful();

    expect(File::exists($this->root.'/'.$this->pointer->live.'/campaigns.html'))->toBeFalse();
    expect(livePage($this, 'index.html'))->not->toContain('/campaigns');
    expect(livePage($this, 'sitemap.xml'))->not->toContain('/campaigns');
    Carbon::setTestNow();
});

it('schedules the daily rebuild', function () {
    $events = collect(app(Schedule::class)->events());

    expect($events->contains(fn ($event) => str_contains((string) $event->command, 'site:rebuild')))->toBeTrue();
});

it('writes a sitemap with every language, and keeps personal links out of search', function () {
    editTitle($this, 'Yeni');
    publishSite($this)->assertOk();

    $sitemap = livePage($this, 'sitemap.xml');
    expect(substr_count($sitemap, '<url>'))->toBe(30);
    expect($sitemap)->toContain('<loc>https://sezam.salam.az/en/services</loc>')
        ->toContain('<loc>https://sezam.salam.az/</loc>')
        ->toContain('hreflang="ru" href="https://sezam.salam.az/ru/faq"');
    expect(simplexml_load_string($sitemap))->not->toBeFalse();

    expect(livePage($this, 'robots.txt'))->toContain('Disallow: /v/')->toContain('Disallow: /invite/')
        ->toContain('Sitemap: https://sezam.salam.az/sitemap.xml');
});

it('previews a saved draft for the panel', function () {
    editTitle($this, 'Önbaxış mətni');

    $html = $this->actingAs($this->admin, 'admin')->getJson('/admin/v1/site/preview/services?lang=az')->assertOk()->json('data.html');

    expect($html)->toContain('Önbaxış mətni')->toContain('<base href="https://sezam.salam.az/"/>');
    expect($this->pointer->moves)->toBe(0);
});

it('shows where an unpublished image waits in a preview', function () {
    $doc = $this->actingAs($this->admin, 'admin')->getJson('/admin/v1/site/documents/contact')->json('data');
    $content = $doc['content'];
    $content['sections'][0]['image'] = '/landing/media/abc.webp';
    $this->actingAs($this->admin, 'admin')->putJson('/admin/v1/site/documents/contact', ['content' => $content, 'version' => 1])->assertOk();

    $html = $this->actingAs($this->admin, 'admin')->getJson('/admin/v1/site/preview/contact')->json('data.html');

    expect($html)->toContain('src="/storage/site-media/abc.webp"');
});

it('previews nothing for a page that would not exist', function () {
    $this->actingAs($this->admin, 'admin')->getJson('/admin/v1/site/preview/campaigns')->assertOk()->assertJsonPath('data.html', null);
});

it('refuses a language the site does not have', function () {
    $this->actingAs($this->admin, 'admin')->getJson('/admin/v1/site/preview/services?lang=de')->assertStatus(422);
});

it('lets an operator publish, and nobody else without the permission', function () {
    editTitle($this, 'Yeni');
    $this->actingAs(makeAdminRole('operator'), 'admin')->postJson('/admin/v1/site/publish')->assertOk();

    foreach (['finance', 'support', 'technical', 'complex_manager'] as $role) {
        $admin = makeAdminRole($role);
        $this->actingAs($admin, 'admin')->getJson('/admin/v1/site/publish')->assertForbidden();
        $this->actingAs($admin, 'admin')->postJson('/admin/v1/site/publish')->assertForbidden();
        $this->actingAs($admin, 'admin')->postJson('/admin/v1/site/releases/1/rollback')->assertForbidden();
        $this->actingAs($admin, 'admin')->getJson('/admin/v1/site/preview/services')->assertForbidden();
    }
});
