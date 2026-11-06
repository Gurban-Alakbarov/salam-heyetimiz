<?php

use App\Domain\Site\Publishing\SectionCatalog;
use App\Domain\Site\Publishing\SiteBuilder;
use App\Domain\Site\Publishing\SiteContent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->work = sys_get_temp_dir().'/site-build-test-'.uniqid();
    File::copyDirectory(base_path('site/content'), $this->work.'/content');
});

afterEach(fn () => File::deleteDirectory($this->work));

function editPage(string $dir, string $key, callable $edit): void
{
    $file = "{$dir}/content/pages/{$key}.json";
    $page = json_decode(file_get_contents($file), true);
    file_put_contents($file, json_encode($edit($page)));
}

function buildSite(string $dir, ?Carbon $today = null): array
{
    $files = (new SiteBuilder(base_path('site')))->build(SiteContent::fromFiles($dir.'/content', $today), $dir.'/out');

    return $files;
}

function sampleCampaign(array $over = []): array
{
    return array_merge([
        'title' => ['az' => 'Yaz endirimi', 'en' => 'Spring sale', 'ru' => 'Весенняя скидка'],
        'text' => ['az' => 'Mətn', 'en' => 'Text', 'ru' => 'Текст'],
        'image' => '/landing/img-03.webp',
        'image_alt' => ['az' => '', 'en' => '', 'ru' => ''],
        'ends_at' => '2026-12-31',
    ], $over);
}

function addCampaign(string $dir, array $campaign): void
{
    editPage($dir, 'campaigns', function (array $page) use ($campaign) {
        $page['sections'][1]['items'][] = $campaign;

        return $page;
    });
}

it('writes one page per address, in every language, with the same addresses as before', function () {
    $files = buildSite($this->work);

    expect($files)->toHaveCount(30)->toContain('index.html', 'privacy.html', 'en/index.html', 'en/privacy.html', 'ru/account.html');
    expect(File::exists($this->work.'/out/landing/site.css'))->toBeTrue();
    expect(File::exists($this->work.'/out/landing/site.js'))->toBeTrue();
    expect(File::exists($this->work.'/out/landing/img-02.webp'))->toBeTrue();
});

it('gives each page its own title, canonical address and language links', function () {
    buildSite($this->work);

    $az = file_get_contents($this->work.'/out/services.html');
    $en = file_get_contents($this->work.'/out/en/services.html');

    expect($az)->toContain('<title>Xidmətlər | Salam Sesame</title>')
        ->toContain('<link rel="canonical" href="https://sezam.salam.az/services"/>')
        ->toContain('hreflang="ru" href="https://sezam.salam.az/ru/services"')
        ->toContain('<a class="nav-link active" href="/services">')
        ->toContain('<a data-lang="en" href="/en/services">');

    expect($en)->toContain('<html lang="en">')
        ->toContain('<title>Services | Salam Sesame</title>')
        ->toContain('<link rel="canonical" href="https://sezam.salam.az/en/services"/>')
        ->toContain('<a data-lang="en" href="/en/services" class="active">')
        ->toContain('<a data-lang="az" href="/services">');
});

it('keeps visitors in their language', function () {
    buildSite($this->work);

    foreach (['en', 'ru'] as $lang) {
        foreach (File::files("{$this->work}/out/{$lang}") as $file) {
            $html = preg_replace('/<a data-lang[^>]*>/', '', file_get_contents($file->getPathname()));

            preg_match_all('/<a[^>]* href="(\/[^"]*)"/', $html, $links);
            expect($links[1])->not->toBeEmpty();
            foreach ($links[1] as $href) {
                expect($href)->toStartWith("/{$lang}/");
            }
        }
    }
});

it('does not repeat one description across the legal pages', function () {
    buildSite($this->work);

    $descriptions = collect(['privacy', 'terms', 'refund', 'account'])->map(function (string $key) {
        preg_match('/<meta content="([^"]*)" name="description"/', file_get_contents("{$this->work}/out/{$key}.html"), $m);

        return $m[1];
    });

    expect($descriptions->unique())->toHaveCount(4);
});

it('follows the section order in the content, and leaves out removed sections', function () {
    editPage($this->work, 'services', function (array $page) {
        $page['sections'] = [$page['sections'][3], $page['sections'][0], $page['sections'][2]];

        return $page;
    });

    buildSite($this->work);
    $html = file_get_contents($this->work.'/out/services.html');

    expect($html)->not->toContain('class="service-grid"');
    expect(strpos($html, 'centered-final'))->toBeLessThan(strpos($html, 'class="page-hero"'));
    expect(strpos($html, 'class="page-hero"'))->toBeLessThan(strpos($html, 'class="functions-grid"'));
});

it('has no campaigns page and no menu link while nothing runs', function () {
    $files = buildSite($this->work);

    expect($files)->not->toContain('campaigns.html');
    expect(file_get_contents($this->work.'/out/index.html'))->not->toContain('/campaigns');
});

it('adds the campaigns page and menu link when a campaign runs', function () {
    addCampaign($this->work, sampleCampaign());

    $files = buildSite($this->work, Carbon::parse('2026-11-10'));

    expect($files)->toHaveCount(33)->toContain('campaigns.html', 'en/campaigns.html', 'ru/campaigns.html');
    expect(file_get_contents($this->work.'/out/en/index.html'))->toContain('<a class="nav-link" href="/en/campaigns">Campaigns</a>');
    expect(file_get_contents($this->work.'/out/ru/campaigns.html'))->toContain('Весенняя скидка')->toContain('31.12.2026');
});

it('keeps a campaign through its last day and drops it the day after', function () {
    addCampaign($this->work, sampleCampaign(['ends_at' => '2026-11-10']));

    expect(buildSite($this->work, Carbon::parse('2026-11-10')))->toContain('campaigns.html');

    File::deleteDirectory($this->work.'/out');

    expect(buildSite($this->work, Carbon::parse('2026-11-11')))->not->toContain('campaigns.html');
});

it('falls back to Azerbaijani where a translation is missing', function () {
    editPage($this->work, 'services', function (array $page) {
        $page['sections'][1]['title']['en'] = '';

        return $page;
    });

    buildSite($this->work);

    expect(file_get_contents($this->work.'/out/en/services.html'))->toContain('Mövcud sistemlərinizlə işləyən həll.');
});

it('stops the build on an unknown section type', function () {
    editPage($this->work, 'services', function (array $page) {
        $page['sections'][1]['type'] = 'nope';

        return $page;
    });

    buildSite($this->work);
})->throws(RuntimeException::class, 'Unknown website section type: nope');

it('refuses a folder that already has files', function () {
    File::ensureDirectoryExists($this->work.'/out');
    File::put($this->work.'/out/keep.txt', 'x');

    buildSite($this->work);
})->throws(RuntimeException::class, 'Build folder is not empty');

it('has a template for every section type', function () {
    foreach (array_keys(SectionCatalog::types()) as $type) {
        expect(view()->exists('site.sections.'.$type))->toBeTrue();
    }
});
