<?php

/*
| API documentation surface: OpenAPI spec serving, Swagger UI, ReDoc, versioning,
| production access-gating, and the Postman/Bruno generator command.
*/

it('serves the merged OpenAPI 3.1 spec as JSON', function () {
    $res = $this->get('/api/openapi.json')->assertOk();
    $res->assertHeader('Content-Type', 'application/json; charset=utf-8');

    $spec = $res->json();
    expect($spec['openapi'])->toBe('3.1.0')
        ->and($spec['info']['version'])->toBe('1.3.0')
        ->and($spec['servers'][0]['url'])->toBe('https://api.salamheyetimiz.com');

    // Real endpoints (mobile + admin + webhook + new) are all present and absolutely pathed.
    expect($spec['paths'])->toHaveKeys([
        '/v1/auth/otp/request',
        '/v1/auth/refresh',
        '/v1/orders',
        '/v1/devices/{deviceId}/open',
        '/admin/v1/auth/login',
        '/admin/v1/orders/{orderId}/refund',
        '/admin/v1/payments/stats',
        '/admin/v1/payment-logs',
        '/admin/v1/access/roles',
        '/admin/v1/complexes',
        '/admin/v1/residents',
        '/admin/v1/system/health',
        '/v1/payments/webhook',
        '/v1/traccar/forward',
    ]);
});

it('serves the versioned spec at /api/v1/openapi.json', function () {
    $spec = $this->get('/api/v1/openapi.json')->assertOk()->json();
    expect($spec['openapi'])->toBe('3.1.0');
});

it('renders Swagger UI and ReDoc as HTML', function () {
    $swagger = $this->get('/api/docs')->assertOk();
    expect($swagger->headers->get('Content-Type'))->toContain('text/html');
    expect($swagger->getContent())->toContain('swagger-ui')->toContain('/api/openapi.json');

    $this->get('/api/docs/swagger')->assertOk();

    $redoc = $this->get('/api/redoc')->assertOk();
    expect($redoc->getContent())->toContain('redoc')->toContain('spec-url');
});

it('serves the raw YAML source', function () {
    $res = $this->get('/api/openapi.yaml')->assertOk();
    expect($res->headers->get('Content-Type'))->toContain('application/yaml');
    expect($res->getContent())->toContain('openapi: 3.1.0');
});

it('hides the docs entirely when disabled', function () {
    config(['docs.enabled' => false]);
    $this->get('/api/openapi.json')->assertNotFound();
    $this->get('/api/docs')->assertNotFound();
});

it('gates the docs behind HTTP Basic Auth in protected mode', function () {
    config(['docs.enabled' => true, 'docs.protect' => true, 'docs.username' => 'docs', 'docs.password' => 's3cret']);

    // Anonymous → 401 challenge (the spec is hidden too).
    $this->get('/api/openapi.json')->assertStatus(401)->assertHeader('WWW-Authenticate');
    $this->get('/api/docs')->assertStatus(401);

    // Wrong credentials → 401.
    $this->withHeaders(['Authorization' => 'Basic '.base64_encode('docs:wrong')])->get('/api/openapi.json')->assertStatus(401);

    // Correct credentials → 200.
    $this->withHeaders(['Authorization' => 'Basic '.base64_encode('docs:s3cret')])->get('/api/openapi.json')->assertOk();
});

it('fails closed when protection is on but no password is configured', function () {
    config(['docs.enabled' => true, 'docs.protect' => true, 'docs.username' => 'docs', 'docs.password' => '']);
    $this->withHeaders(['Authorization' => 'Basic '.base64_encode('docs:')])->get('/api/openapi.json')->assertStatus(401);
});

it('generates valid Postman + Bruno collections', function () {
    $this->artisan('docs:generate')->assertSuccessful();

    $postman = json_decode(file_get_contents(base_path('docs/postman/Salam.postman_collection.json')), true);
    expect($postman['info']['schema'])->toContain('v2.1.0')
        ->and($postman['item'])->not->toBeEmpty()
        ->and($postman['variable'])->toContain(['key' => 'baseUrl', 'value' => 'https://api.salamheyetimiz.com']);

    expect(file_exists(base_path('docs/bruno/bruno.json')))->toBeTrue()
        ->and(file_exists(base_path('docs/bruno/environments/Production.bru')))->toBeTrue();
});
