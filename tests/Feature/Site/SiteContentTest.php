<?php

use App\Domain\Site\Content\SiteHtml;
use App\Domain\Site\Models\SiteDocument;
use App\Domain\Site\Models\SiteRevision;
use App\Domain\Site\Publishing\SiteBuilder;
use App\Domain\Site\Publishing\SiteContent;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    seedRbac();
    $this->artisan('site:import')->assertSuccessful();
    $this->admin = makeSuperAdmin();
});

function siteDoc(object $test, string $key): array
{
    return $test->actingAs($test->admin, 'admin')->getJson("/admin/v1/site/documents/{$key}")->assertOk()->json('data');
}

function saveDoc(object $test, string $key, array $content, int $version)
{
    return $test->actingAs($test->admin, 'admin')->putJson("/admin/v1/site/documents/{$key}", ['content' => $content, 'version' => $version]);
}

it('imports every page and the shared texts, counted as published, and only once', function () {
    expect(SiteDocument::query()->count())->toBe(12);
    expect(SiteDocument::query()->pluck('key'))->toContain('site', 'index', 'campaigns', 'privacy');
    expect(SiteDocument::query()->get()->every(fn (SiteDocument $d) => ! $d->hasUnpublishedChanges()))->toBeTrue();

    $this->artisan('site:import')->assertSuccessful();

    expect(SiteDocument::query()->count())->toBe(12);
    expect(SiteRevision::query()->count())->toBe(12);
});

it('builds the same website from the database as from the files', function () {
    $dir = sys_get_temp_dir().'/site-db-test-'.uniqid();
    $builder = new SiteBuilder(base_path('site'));

    $fromFiles = $builder->build(SiteContent::fromFiles(base_path('site/content')), $dir.'/files');
    $fromDb = $builder->build(SiteContent::fromDatabase(base_path('site/content/icons.json')), $dir.'/db');

    expect($fromDb)->toEqualCanonicalizing($fromFiles);
    foreach ($fromFiles as $file) {
        // Only the spelling of a line break differs after cleaning: <br/> and <br>.
        $a = str_replace('<br/>', '<br>', file_get_contents("{$dir}/files/{$file}"));
        $b = str_replace('<br/>', '<br>', file_get_contents("{$dir}/db/{$file}"));
        expect($b)->toBe($a, $file);
    }

    File::deleteDirectory($dir);
});

it('starts with no translation problems', function () {
    $list = $this->actingAs($this->admin, 'admin')->getJson('/admin/v1/site/documents')->assertOk()->json('data');

    expect($list)->toHaveCount(12);
    foreach ($list as $row) {
        expect($row['translations_stale'])->toBe(0);
    }
});

it('gives the panel the section types, fields and icons', function () {
    $this->actingAs($this->admin, 'admin')->getJson('/admin/v1/site/catalog')
        ->assertOk()
        ->assertJsonPath('data.languages', ['az', 'en', 'ru'])
        ->assertJsonPath('data.section_types.campaign-list.label', 'Kampaniyalar')
        ->assertJsonPath('data.section_types.legal.fields.items.fields.body.kind', 'rich')
        ->assertJsonPath('data.shared_fields.menu_open.label', 'Menyunu aç');
});

it('saves a change as a new version, audited, and marks it unpublished', function () {
    $doc = siteDoc($this, 'services');
    $content = $doc['content'];
    $content['sections'][1]['title']['az'] = 'Yeni başlıq';

    $saved = saveDoc($this, 'services', $content, 1)->assertOk()->json('data');

    expect($saved['version'])->toBe(2)->and($saved['has_unpublished_changes'])->toBeTrue();
    expect($saved['content']['sections'][1]['title']['az'])->toBe('Yeni başlıq');
    expect(SiteRevision::query()->where('version', 2)->count())->toBe(1);
    $this->assertDatabaseHas('audit_logs', ['action' => 'site_document.updated']);
});

it('does not make a new version when nothing changed', function () {
    $doc = siteDoc($this, 'services');

    expect(saveDoc($this, 'services', $doc['content'], 1)->assertOk()->json('data.version'))->toBe(1);
    expect(SiteRevision::query()->where('version', 2)->count())->toBe(0);
});

it('stops a second admin from overwriting the first', function () {
    $doc = siteDoc($this, 'services');
    $content = $doc['content'];
    $content['sections'][1]['title']['az'] = 'Birinci';
    saveDoc($this, 'services', $content, 1)->assertOk();

    $content['sections'][1]['title']['az'] = 'İkinci';
    saveDoc($this, 'services', $content, 1)
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'site_content_conflict');

    expect(siteDoc($this, 'services')['content']['sections'][1]['title']['az'])->toBe('Birinci');
});

it('moves, removes and adds sections', function () {
    $doc = siteDoc($this, 'services');
    $content = $doc['content'];
    $content['sections'] = [
        $content['sections'][3],
        $content['sections'][0],
        ['type' => 'cta-dark', 'title' => ['az' => 'Yeni çağırış']],
    ];

    $saved = saveDoc($this, 'services', $content, 1)->assertOk()->json('data.content.sections');

    expect(array_column($saved, 'type'))->toBe(['cta-center', 'page-hero', 'cta-dark']);
    // A new section gets every key its template reads, empty.
    expect($saved[2]['button'])->toHaveKeys(['label', 'href']);
    expect($saved[2]['text']['ru'])->toBe('');
});

it('keeps only what a section type has', function () {
    $doc = siteDoc($this, 'services');
    $content = $doc['content'];
    $content['sections'][1]['extra'] = 'x';

    $saved = saveDoc($this, 'services', $content, 1)->assertOk()->json('data.content.sections.1');

    expect($saved)->not->toHaveKey('extra');
});

it('refuses what cannot be right', function (string $path, mixed $value, string $errorKey) {
    $doc = siteDoc($this, 'index');
    $content = $doc['content'];
    data_set($content, $path, $value);

    saveDoc($this, 'index', $content, 1)->assertStatus(422)->assertJsonValidationErrors([$errorKey], 'error.fields');
})->with([
    'section type' => ['sections.1.type', 'nope', 'sections.1.type'],
    'script link' => ['sections.0.primary.href', 'javascript:alert(1)', 'sections.0.primary.href'],
    'image elsewhere' => ['sections.0.image', 'https://evil.example/a.png', 'sections.0.image'],
    'icon' => ['sections.1.items.0.icon', 'nope', 'sections.1.items.0.icon'],
]);

it('refuses a date that does not exist', function () {
    $doc = siteDoc($this, 'campaigns');
    $content = $doc['content'];
    $content['sections'][1]['items'] = [['title' => ['az' => 'A'], 'ends_at' => '2026-02-31']];

    saveDoc($this, 'campaigns', $content, 1)->assertStatus(422);
});

it('removes scripts from edited text', function () {
    $doc = siteDoc($this, 'privacy');
    $content = $doc['content'];
    $content['sections'][1]['items'][0]['body']['az'] = '<p onclick="x()">Mətn</p><script>alert(1)</script><a href="javascript:x()">y</a><img src=x onerror=z>';
    $content['sections'][0]['title']['az'] = 'Başlıq <em>vurğu</em><script>alert(1)</script><p>z</p>';
    $content['sections'][0]['text']['az'] = '<b>plain</b>';

    $saved = saveDoc($this, 'privacy', $content, 1)->assertOk()->json('data.content.sections');

    expect($saved[1]['items'][0]['body']['az'])->toBe('<p>Mətn</p><a>y</a>');
    expect($saved[0]['title']['az'])->toBe('Başlıq <em>vurğu</em>z');

    // Plain text stays text: the template escapes it.
    $dir = sys_get_temp_dir().'/site-esc-test-'.uniqid();
    $html = (new SiteBuilder(base_path('site')))->renderPage(SiteContent::fromDatabase(base_path('site/content/icons.json')), SiteDocument::query()->where('key', 'privacy')->first()->content, 'az');
    expect($html)->toContain('&lt;b&gt;plain&lt;/b&gt;')->not->toContain('<script>alert');
});

it('keeps what the content uses when cleaning', function () {
    expect(SiteHtml::rich('<h3>A</h3><p>Salam <strong>b</strong> <em>c</em></p><ul><li>ə</li></ul>'))
        ->toBe('<h3>A</h3><p>Salam <strong>b</strong> <em>c</em></p><ul><li>ə</li></ul>');
    expect(SiteHtml::inline('Bir<br>iki <a href="/privacy">link</a> <a href="mailto:a@b.co?subject=x%20y">m</a>'))
        ->toBe('Bir<br>iki <a href="/privacy">link</a> <a href="mailto:a@b.co?subject=x%20y">m</a>');
    expect(SiteHtml::inline('a &amp; b <em>c</em>'))->toBe('a &amp; b <em>c</em>');
});

it('allows only safe link addresses', function (string $href, bool $safe) {
    expect(SiteHtml::safeHref($href))->toBe($safe);
})->with([
    ['/contact', true],
    ['https://salam.az/x', true],
    ['mailto:a@b.co', true],
    ['tel:+994501112233', true],
    ['#offer', true],
    ['//evil.example', false],
    ['javascript:alert(1)', false],
    ['JaVaScRiPt:alert(1)', false],
    ['data:text/html,x', false],
]);

it('marks translations when the Azerbaijani changes, and clears the mark when they follow', function () {
    $doc = siteDoc($this, 'services');
    $content = $doc['content'];
    $content['sections'][1]['title']['az'] = 'Dəyişdi';

    $saved = saveDoc($this, 'services', $content, 1)->json('data');

    expect($saved['translation_issues'])->toEqualCanonicalizing([
        ['path' => 'sections.1.title', 'lang' => 'en', 'state' => 'stale'],
        ['path' => 'sections.1.title', 'lang' => 'ru', 'state' => 'stale'],
    ]);

    $content = $saved['content'];
    $content['sections'][1]['title']['en'] = 'Changed';
    $saved = saveDoc($this, 'services', $content, 2)->json('data');

    expect($saved['translation_issues'])->toBe([['path' => 'sections.1.title', 'lang' => 'ru', 'state' => 'stale']]);
});

it('lets an admin confirm a translation is still right', function () {
    $doc = siteDoc($this, 'services');
    $content = $doc['content'];
    $content['sections'][1]['title']['az'] = 'Dəyişdi';
    $content = saveDoc($this, 'services', $content, 1)->json('data.content');

    $content['sections'][1]['title']['_confirmed'] = ['en', 'ru'];
    $saved = saveDoc($this, 'services', $content, 2)->json('data');

    expect($saved['translation_issues'])->toBe([]);
    expect($saved['content']['sections'][1]['title'])->not->toHaveKey('_confirmed');
});

it('keeps the mark on the right text when sections move', function () {
    $doc = siteDoc($this, 'services');
    $content = $doc['content'];
    $content['sections'][1]['title']['az'] = 'Dəyişdi';
    $content = saveDoc($this, 'services', $content, 1)->json('data.content');

    $content['sections'] = array_reverse($content['sections']);
    $issues = saveDoc($this, 'services', $content, 2)->json('data.translation_issues');

    expect(array_unique(array_column($issues, 'path')))->toBe(['sections.2.title']);
});

it('reports a missing translation', function () {
    $doc = siteDoc($this, 'services');
    $content = $doc['content'];
    $content['sections'][1]['title']['ru'] = '';

    expect(saveDoc($this, 'services', $content, 1)->json('data.translation_issues'))
        ->toBe([['path' => 'sections.1.title', 'lang' => 'ru', 'state' => 'missing']]);
});

it('edits the shared texts but not the menus', function () {
    $doc = siteDoc($this, 'site');
    $content = $doc['content'];
    $content['texts']['cta_label']['az'] = 'Qiymət soruş';
    $content['menu'] = ['index'];

    $saved = saveDoc($this, 'site', $content, 1)->assertOk()->json('data.content');

    expect($saved['texts']['cta_label']['az'])->toBe('Qiymət soruş');
    expect($saved['menu'])->toContain('services', 'faq');
});

it('lists versions and brings an earlier one back as a new version', function () {
    $doc = siteDoc($this, 'services');
    $original = $doc['content']['sections'][1]['title']['az'];
    $content = $doc['content'];
    $content['sections'][1]['title']['az'] = 'Səhv mətn';
    saveDoc($this, 'services', $content, 1)->assertOk();

    $list = $this->actingAs($this->admin, 'admin')->getJson('/admin/v1/site/documents/services/revisions')->assertOk()->json('data');
    expect(array_column($list, 'version'))->toBe([2, 1]);
    expect($list[1]['published'])->toBeTrue();
    expect($list[0]['admin'])->toBe($this->admin->name);

    $this->actingAs($this->admin, 'admin')->getJson('/admin/v1/site/documents/services/revisions/1')
        ->assertOk()->assertJsonPath('data.content.sections.1.title.az', $original);

    $restored = $this->actingAs($this->admin, 'admin')
        ->postJson('/admin/v1/site/documents/services/revisions/1/restore', ['version' => 2])
        ->assertOk()->json('data');

    expect($restored['version'])->toBe(3);
    expect($restored['content']['sections'][1]['title']['az'])->toBe($original);
    expect(SiteRevision::query()->where('version', 3)->value('restored_from'))->toBe(1);
    $this->assertDatabaseHas('audit_logs', ['action' => 'site_document.restored']);
});

it('keeps fifty versions and the published one', function () {
    $doc = siteDoc($this, 'services');
    $content = $doc['content'];
    foreach (range(1, 55) as $i) {
        $content['sections'][1]['title']['az'] = "Başlıq {$i}";
        saveDoc($this, 'services', $content, $i)->assertOk();
    }

    $versions = SiteDocument::query()->where('key', 'services')->first()->revisions()->pluck('version')->all();

    expect($versions)->toHaveCount(51)->toContain(1, 7, 56)->not->toContain(2, 6);
});

it('answers 404 for a page that does not exist', function () {
    $this->actingAs($this->admin, 'admin')->getJson('/admin/v1/site/documents/nope')->assertNotFound();
});

it('lets an operator edit, and nobody else without the permission', function () {
    $operator = makeAdminRole('operator');
    $this->actingAs($operator, 'admin')->getJson('/admin/v1/site/documents')->assertOk();

    foreach (['finance', 'support', 'technical', 'complex_manager'] as $role) {
        $admin = makeAdminRole($role);
        $this->actingAs($admin, 'admin')->getJson('/admin/v1/site/documents')->assertForbidden();
        $this->actingAs($admin, 'admin')->getJson('/admin/v1/site/catalog')->assertForbidden();
        $this->actingAs($admin, 'admin')->putJson('/admin/v1/site/documents/services', ['content' => ['x' => 1], 'version' => 1])->assertForbidden();
    }
});
