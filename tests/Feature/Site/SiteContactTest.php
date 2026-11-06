<?php

use App\Domain\Admin\Services\SettingsService;
use App\Domain\Admin\Settings\RuntimeMailer;
use App\Domain\Audit\Models\AuditLog;
use App\Domain\Site\Jobs\SendSiteLeadEmailJob;
use App\Domain\Site\Models\SiteLead;
use Illuminate\Support\Facades\Bus;

/*
| The contact form on the public website: requests reach the admin panel, and an e-mail when an address is set.
*/

beforeEach(fn () => seedRbac());

function siteLeadBody(array $over = []): array
{
    return array_merge([
        'name' => 'Rauf Həsənov', 'email' => 'rauf@example.test', 'phone' => '+994501110001',
        'place' => 'Yaşayış kompleksi', 'topic' => 'Təklif almaq', 'message' => 'Blok A üçün şlaqbaum', 'locale' => 'az',
    ], $over);
}

it('stores a website request and queues the team e-mail', function () {
    Bus::fake([SendSiteLeadEmailJob::class]);

    $this->postJson('/v1/site/contact', siteLeadBody())->assertStatus(202)->assertJsonPath('ok', true);

    $lead = SiteLead::query()->firstOrFail();
    expect($lead->name)->toBe('Rauf Həsənov')->and($lead->status->value)->toBe('new');
    Bus::assertDispatched(SendSiteLeadEmailJob::class, fn ($job) => $job->leadId === $lead->id);
});

it('refuses an incomplete request', function (array $over) {
    $this->postJson('/v1/site/contact', siteLeadBody($over))->assertStatus(422);
    expect(SiteLead::query()->count())->toBe(0);
})->with([
    'no name' => [['name' => '']],
    'bad e-mail' => [['email' => 'nope']],
    'no phone' => [['phone' => '']],
    'no place' => [['place' => '']],
]);

it('answers a bot the same way and stores nothing', function () {
    Bus::fake([SendSiteLeadEmailJob::class]);

    $this->postJson('/v1/site/contact', siteLeadBody(['website' => 'http://spam.example']))->assertStatus(202);

    expect(SiteLead::query()->count())->toBe(0);
    Bus::assertNotDispatched(SendSiteLeadEmailJob::class);
});

it('limits how often one e-mail address can send, not everyone behind the proxy', function () {
    Bus::fake([SendSiteLeadEmailJob::class]);

    foreach (range(1, 3) as $i) {
        $this->postJson('/v1/site/contact', siteLeadBody())->assertStatus(202);
    }

    $this->postJson('/v1/site/contact', siteLeadBody())->assertStatus(429);
    $this->postJson('/v1/site/contact', siteLeadBody(['email' => 'other@example.test']))->assertStatus(202);
});

it('e-mails the address from Settings', function () {
    $mailer = new class(app(SettingsService::class)) extends RuntimeMailer
    {
        /** @var array<int, array{to: string, subject: string, body: string, html: ?string}> */
        public array $sent = [];

        public function isConfigured(): bool
        {
            return true;
        }

        public function send(string $to, string $subject, string $body, ?string $html = null): void
        {
            $this->sent[] = compact('to', 'subject', 'body', 'html');
        }
    };
    app()->instance(RuntimeMailer::class, $mailer);
    app(SettingsService::class)->setGroup('general', ['site_lead_email' => 'team@example.test']);

    $this->postJson('/v1/site/contact', siteLeadBody())->assertStatus(202);

    expect($mailer->sent)->toHaveCount(1)
        ->and($mailer->sent[0]['to'])->toBe('team@example.test')
        ->and($mailer->sent[0]['body'])->toContain('Rauf Həsənov')->toContain('+994501110001');
});

it('keeps the request when no address is set', function () {
    $this->postJson('/v1/site/contact', siteLeadBody())->assertStatus(202);

    expect(SiteLead::query()->count())->toBe(1);
});

it('lets an admin list, search and update requests, audited', function () {
    Bus::fake([SendSiteLeadEmailJob::class]);
    $this->postJson('/v1/site/contact', siteLeadBody());
    $this->postJson('/v1/site/contact', siteLeadBody(['name' => 'Aysel Quliyeva', 'email' => 'aysel@example.test']));
    $admin = makeSuperAdmin();

    $this->actingAs($admin, 'admin')->getJson('/admin/v1/site-leads?q=aysel')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.name', 'Aysel Quliyeva');

    $id = SiteLead::query()->where('email', 'aysel@example.test')->value('id');
    $this->actingAs($admin, 'admin')->patchJson("/admin/v1/site-leads/{$id}", ['status' => 'contacted', 'admin_note' => 'Zəng edildi'])
        ->assertOk()
        ->assertJsonPath('data.status', 'contacted')
        ->assertJsonPath('data.admin_note', 'Zəng edildi');

    expect(AuditLog::query()->where('action', 'site_lead.updated')->count())->toBe(1);

    $this->actingAs($admin, 'admin')->getJson('/admin/v1/site-leads?status=new')->assertJsonCount(1, 'data');
});

it('hides requests from admins without the permission', function () {
    $complex = makeComplex('CX-L', 'L');

    $this->actingAs(makeAdminRole('complex_manager', (int) $complex->id), 'admin')
        ->getJson('/admin/v1/site-leads')->assertForbidden();
    $this->actingAs(makeAdminRole('finance'), 'admin')
        ->getJson('/admin/v1/site-leads')->assertForbidden();
});
