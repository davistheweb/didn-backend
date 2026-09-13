<?php

use App\Models\Event;
use App\Models\Media;
use App\Models\Post;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('requires authentication for media endpoints', function () {
    $this->postJson('/api/v1/admin/media')->assertStatus(401);
    $this->deleteJson('/api/v1/admin/media/1')->assertStatus(401);
});

it('uploads an image and returns a usable url', function () {
    actingAsAdmin();
    Storage::fake('public');

    $this->postJson('/api/v1/admin/media', [
        'file' => fakeImageUpload('cover.jpg'),
    ])
        ->assertStatus(201)
        ->assertJsonPath('success', true)
        ->assertJsonStructure(['data' => ['id', 'url', 'original_name', 'mime_type', 'size']])
        ->assertJsonPath('data.original_name', 'cover.jpg')
        ->assertJsonPath('data.mime_type', 'image/jpeg')
        ->assertJsonMissingPath('data.path');

    $media = Media::query()->first();

    expect($media)->not->toBeNull()
        ->and($media->path)->toStartWith('backend/uploads/')
        ->and(Storage::disk('public')->exists($media->path))->toBeTrue()
        ->and($media->url)->toContain('/storage/backend/');
});

it('rejects non-image files and oversized files', function () {
    actingAsAdmin();
    Storage::fake('public');

    $this->postJson('/api/v1/admin/media', [
        'file' => UploadedFile::fake()->create('notes.txt', 10),
    ])
        ->assertStatus(422)
        ->assertJsonPath('success', false)
        ->assertJsonStructure(['errors' => ['file']]);

    $this->assertDatabaseCount('media', 0);
});

it('deletes media and detaches it from events and posts', function () {
    $admin = actingAsAdmin();
    Storage::fake('public');

    $media = Media::create([
        'disk' => 'public',
        'path' => 'backend/uploads/2026/09/to-delete.jpg',
        'original_name' => 'to-delete.jpg',
        'mime_type' => 'image/jpeg',
        'size' => 100,
    ]);

    Storage::disk('public')->put($media->path, 'bytes');

    $event = Event::create([
        'title' => 'With Image',
        'slug' => 'with-image',
        'event_type' => 'webinar',
        'start_date' => now()->addDay(),
        'featured_image_id' => $media->id,
    ]);

    $post = Post::create([
        'title' => 'With Cover',
        'slug' => 'with-cover',
        'content' => '<p>x</p>',
        'cover_image_id' => $media->id,
        'status' => 'draft',
        'author_id' => $admin->id,
    ]);

    $this->deleteJson("/api/v1/admin/media/{$media->id}")
        ->assertOk()
        ->assertJsonPath('success', true);

    expect(Storage::disk('public')->exists($media->path))->toBeFalse()
        ->and($event->fresh()->featured_image_id)->toBeNull()
        ->and($post->fresh()->cover_image_id)->toBeNull();
});
