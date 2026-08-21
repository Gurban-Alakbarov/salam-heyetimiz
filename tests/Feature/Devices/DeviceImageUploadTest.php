<?php

use App\Domain\Devices\Models\Device;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/*
| Admin barrier-photo upload: admin-only, validated (real image + MIME + extension + ≤5MB), stored on the
| public disk under a random UUID, image_url set to the public URL. Replacing/deleting removes the old
| local file; external URLs are never touched (backward compatible).
*/

function fakePng(string $name = 'barrier.png'): UploadedFile
{
    // A real 1×1 PNG (valid for getimagesize) — works without the GD extension.
    $bytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAAC0lEQVR42mNk+M8AAAMBAQDJ/pLvAAAAAElFTkSuQmCC');

    return UploadedFile::fake()->createWithContent($name, $bytes);
}

function jsonHeaders(): array
{
    return ['Accept' => 'application/json'];
}

it('rejects an unauthenticated upload (401)', function () {
    $device = makeUnassignedDevice('IMG-401', '+994700900001');

    $this->post("/admin/v1/devices/{$device->id}/image", ['image' => fakePng()], jsonHeaders())
        ->assertUnauthorized();
});

it('forbids an admin without devices.update (403)', function () {
    $admin = makeAdminRole('finance', null, 'fin-img@salamhayetimiz.az');
    $device = makeUnassignedDevice('IMG-403', '+994700900002');

    $this->actingAs($admin, 'admin')
        ->post("/admin/v1/devices/{$device->id}/image", ['image' => fakePng()], jsonHeaders())
        ->assertForbidden();
});

it('uploads a photo, stores it on the public disk, and sets image_url', function () {
    Storage::fake('public');
    $admin = makeSuperAdmin();
    $device = makeUnassignedDevice('IMG-OK', '+994700900003');

    $this->actingAs($admin, 'admin')
        ->post("/admin/v1/devices/{$device->id}/image", ['image' => fakePng()], jsonHeaders())
        ->assertOk()
        ->assertJsonPath('id', $device->id);

    $device->refresh();
    expect($device->image_url)->toContain('/storage/devices/')
        ->and(Storage::disk('public')->files('devices'))->toHaveCount(1);
});

it('replaces the previous uploaded photo and deletes the old file', function () {
    Storage::fake('public');
    $admin = makeSuperAdmin();
    $device = makeUnassignedDevice('IMG-REP', '+994700900004');

    $this->actingAs($admin, 'admin')->post("/admin/v1/devices/{$device->id}/image", ['image' => fakePng('a.png')], jsonHeaders())->assertOk();
    $device->refresh();
    $firstName = basename((string) $device->image_url);
    expect(Storage::disk('public')->exists("devices/{$firstName}"))->toBeTrue();

    $this->actingAs($admin, 'admin')->post("/admin/v1/devices/{$device->id}/image", ['image' => fakePng('b.png')], jsonHeaders())->assertOk();

    // old file gone, exactly one file remains
    expect(Storage::disk('public')->exists("devices/{$firstName}"))->toBeFalse()
        ->and(Storage::disk('public')->files('devices'))->toHaveCount(1);
});

it('keeps an external image_url untouched when a new photo is uploaded (backward compatible)', function () {
    Storage::fake('public');
    $admin = makeSuperAdmin();
    $device = makeUnassignedDevice('IMG-EXT', '+994700900005');
    $device->forceFill(['image_url' => 'https://cdn.example.com/legacy/barrier.jpg'])->save();

    $this->actingAs($admin, 'admin')->post("/admin/v1/devices/{$device->id}/image", ['image' => fakePng()], jsonHeaders())->assertOk();

    $device->refresh();
    // now points at our storage, and nothing external was deleted (there was no local file to remove)
    expect($device->image_url)->toContain('/storage/devices/')
        ->and(Storage::disk('public')->files('devices'))->toHaveCount(1);
});

it('rejects a non-image file (422)', function () {
    $admin = makeSuperAdmin();
    $device = makeUnassignedDevice('IMG-BAD', '+994700900006');
    $bad = UploadedFile::fake()->createWithContent('notes.txt', 'just text, not an image');

    $this->actingAs($admin, 'admin')
        ->post("/admin/v1/devices/{$device->id}/image", ['image' => $bad], jsonHeaders())
        ->assertStatus(422)
        ->assertJsonStructure(['error' => ['fields' => ['image']]]);
});

it('rejects an oversized file (422)', function () {
    $admin = makeSuperAdmin();
    $device = makeUnassignedDevice('IMG-BIG', '+994700900007');
    $big = UploadedFile::fake()->create('big.png', 6000, 'image/png'); // 6 MB > 5 MB cap

    $this->actingAs($admin, 'admin')
        ->post("/admin/v1/devices/{$device->id}/image", ['image' => $big], jsonHeaders())
        ->assertStatus(422)
        ->assertJsonStructure(['error' => ['fields' => ['image']]]);
});

it('deletes the photo via the DELETE endpoint (file + column)', function () {
    Storage::fake('public');
    $admin = makeSuperAdmin();
    $device = makeUnassignedDevice('IMG-DEL', '+994700900008');

    $this->actingAs($admin, 'admin')->post("/admin/v1/devices/{$device->id}/image", ['image' => fakePng()], jsonHeaders())->assertOk();
    $device->refresh();
    $name = basename((string) $device->image_url);

    $this->actingAs($admin, 'admin')->deleteJson("/admin/v1/devices/{$device->id}/image")->assertOk();

    $device->refresh();
    expect($device->image_url)->toBeNull()
        ->and(Storage::disk('public')->exists("devices/{$name}"))->toBeFalse();
});

it('removes the stored file when the device is deleted', function () {
    Storage::fake('public');
    $admin = makeSuperAdmin();
    $device = makeUnassignedDevice('IMG-DEV-DEL', '+994700900009');

    $this->actingAs($admin, 'admin')->post("/admin/v1/devices/{$device->id}/image", ['image' => fakePng()], jsonHeaders())->assertOk();
    $device->refresh();
    $name = basename((string) $device->image_url);
    expect(Storage::disk('public')->exists("devices/{$name}"))->toBeTrue();

    $device->delete(); // observer purges the file

    expect(Storage::disk('public')->exists("devices/{$name}"))->toBeFalse();
});
