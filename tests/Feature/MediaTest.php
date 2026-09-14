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

it('uploads a blog image into its slug folder and returns a usable url', function () {
    actingAsAdmin();
    Storage::fake('public');

    $this->postJson('/api/v1/admin/media', [
        'type' => 'blog',
        'slug' => 'annual-report',
        'usage' => 'cover',
        'file' => fakeImageUpload('cover.jpg'),
    ])
        ->assertStatus(201)
        ->assertJsonPath('success', true)
        ->assertJsonStructure(['data' => ['id', 'url', 'path', 'original_name', 'mime_type', 'size']])
        ->assertJsonPath('data.original_name', 'cover.jpg')
        ->assertJsonPath('data.mime_type', 'image/jpeg');

    $media = Media::query()->first();

    expect($media)->not->toBeNull()
        ->and($media->path)->toStartWith('blogs/annual-report/cover-')
        ->and($media->path)->toEndWith('.jpg')
        ->and(Storage::disk('public')->exists($media->path))->toBeTrue()
        ->and($media->url)->toContain('/storage/blogs/annual-report/');

    $this->getJson('/api/v1/admin/media')
        ->assertOk()
        ->assertJsonPath('data.0.path', $media->path)
        ->assertJsonPath('data.0.url', $media->url);
});

it('stores uploads without a slug in a pending folder', function () {
    actingAsAdmin();
    Storage::fake('public');

    $this->postJson('/api/v1/admin/media', [
        'type' => 'event',
        'file' => fakeImageUpload('poster.png'),
    ])->assertCreated()->assertJsonPath('success', true);

    $media = Media::query()->first();

    expect($media->path)->toStartWith('events/_pending/')
        ->and($media->path)->toContain('image-')
        ->and(Storage::disk('public')->exists($media->path))->toBeTrue();
});

it('uses cover and image prefixes for the two usages', function () {
    actingAsAdmin();
    Storage::fake('public');

    $this->postJson('/api/v1/admin/media', [
        'type' => 'blog',
        'slug' => 'topic',
        'usage' => 'cover',
        'file' => fakeImageUpload('hero.jpg'),
    ])->assertCreated();

    $this->postJson('/api/v1/admin/media', [
        'type' => 'blog',
        'slug' => 'topic',
        'usage' => 'content',
        'file' => fakeImageUpload('body.jpg'),
    ])->assertCreated();

    $paths = Media::query()->orderBy('id')->pluck('path');

    expect($paths[0])->toContain('/cover-')
        ->and($paths[1])->toContain('/image-');
});

it('rejects unknown types, invalid slugs and usages', function () {
    actingAsAdmin();
    Storage::fake('public');

    $this->postJson('/api/v1/admin/media', [
        'type' => 'gallery',
        'file' => fakeImageUpload('x.jpg'),
    ])->assertStatus(422)->assertJsonStructure(['errors' => ['type']]);

    $this->postJson('/api/v1/admin/media', [
        'type' => 'blog',
        'slug' => 'Uppercase Slug',
        'file' => fakeImageUpload('x.jpg'),
    ])->assertStatus(422)->assertJsonStructure(['errors' => ['slug']]);

    $this->postJson('/api/v1/admin/media', [
        'type' => 'blog',
        'usage' => 'banner',
        'file' => fakeImageUpload('x.jpg'),
    ])->assertStatus(422)->assertJsonStructure(['errors' => ['usage']]);
});

it('rejects non-image files and oversized files', function () {
    actingAsAdmin();
    Storage::fake('public');

    $this->postJson('/api/v1/admin/media', [
        'type' => 'blog',
        'file' => UploadedFile::fake()->create('notes.txt', 10),
    ])
        ->assertStatus(422)
        ->assertJsonPath('success', false)
        ->assertJsonStructure(['errors' => ['file']]);

    $this->assertDatabaseCount('media', 0);
});

it('reconciles a pending upload folder into the blog slug folder on create', function () {
    $admin = actingAsAdmin();
    Storage::fake('public');

    $this->postJson('/api/v1/admin/media', [
        'type' => 'blog',
        'usage' => 'cover',
        'file' => fakeImageUpload('cover.jpg'),
    ])->assertCreated();

    $cover = Media::query()->first();
    $pendingFolder = dirname($cover->path);

    expect($pendingFolder)->toStartWith('blogs/_pending/');

    $this->postJson('/api/v1/admin/posts', [
        'title' => 'Summer Roundup',
        'content' => '<p>Story.</p>',
        'status' => 'published',
        'cover_image_id' => $cover->id,
    ])->assertCreated()->assertJsonPath('data.slug', 'summer-roundup');

    expect($cover->fresh()->path)->toStartWith('blogs/summer-roundup/cover-')
        ->and(Storage::disk('public')->directoryMissing($pendingFolder))->toBeTrue()
        ->and(Storage::disk('public')->exists($cover->fresh()->path))->toBeTrue()
        ->and($cover->fresh()->url)->toContain('/storage/blogs/summer-roundup/');
});

it('reconciles a pending event folder when an event is created', function () {
    actingAsAdmin();
    Storage::fake('public');

    $this->postJson('/api/v1/admin/media', [
        'type' => 'event',
        'usage' => 'cover',
        'file' => fakeImageUpload('poster.jpg'),
    ])->assertCreated();

    $featured = Media::query()->first();

    $this->postJson('/api/v1/admin/events', [
        'title' => 'Youth Summit',
        'description' => 'A summit for young leaders.',
        'event_type' => 'conference',
        'start_date' => now()->addWeek(),
        'featured_image_id' => $featured->id,
    ])->assertCreated()->assertJsonPath('data.slug', 'youth-summit');

    expect($featured->fresh()->path)->toStartWith('events/youth-summit/cover-')
        ->and($featured->fresh()->url)->toContain('/storage/events/youth-summit/');
});

it('moves the media folder when a post slug changes', function () {
    actingAsAdmin();
    Storage::fake('public');

    $this->postJson('/api/v1/admin/media', [
        'type' => 'blog',
        'slug' => 'first-draft',
        'usage' => 'cover',
        'file' => fakeImageUpload('cover.jpg'),
    ])->assertCreated();

    $cover = Media::query()->first();

    $post = Post::factory()->draft()->create([
        'slug' => 'first-draft',
        'cover_image_id' => $cover->id,
        'content' => '<p>Body</p>',
    ]);

    $this->putJson("/api/v1/admin/posts/{$post->id}", [
        'slug' => 'renamed-draft',
        'content' => '<p>Body with '.$cover->url.' untouched</p>',
    ])->assertOk()->assertJsonPath('data.slug', 'renamed-draft');

    expect($cover->fresh()->path)->toStartWith('blogs/renamed-draft/cover-')
        ->and(Storage::disk('public')->directoryMissing('blogs/first-draft'))->toBeTrue()
        ->and($cover->fresh()->url)->toContain('/storage/blogs/renamed-draft/');
});

it('removes the whole content folder when a blog post is deleted', function () {
    $admin = actingAsAdmin();
    Storage::fake('public');

    $this->postJson('/api/v1/admin/media', [
        'type' => 'blog',
        'slug' => 'sunset-story',
        'usage' => 'cover',
        'file' => fakeImageUpload('cover.jpg'),
    ])->assertCreated();

    $this->postJson('/api/v1/admin/media', [
        'type' => 'blog',
        'slug' => 'sunset-story',
        'usage' => 'content',
        'file' => fakeImageUpload('body.jpg'),
    ])->assertCreated();

    $cover = Media::where('path', 'like', 'blogs/sunset-story/cover-%')->firstOrFail();
    $body = Media::where('path', 'like', 'blogs/sunset-story/image-%')->firstOrFail();

    $post = Post::factory()->create([
        'slug' => 'sunset-story',
        'author_id' => $admin->id,
        'cover_image_id' => $cover->id,
    ]);

    $this->deleteJson("/api/v1/admin/posts/{$post->id}")->assertOk();

    expect(Storage::disk('public')->directoryMissing('blogs/sunset-story'))->toBeTrue()
        ->and(Media::find($cover->id))->toBeNull()
        ->and(Media::find($body->id))->toBeNull();
});

it('keeps media that another post still references when deleting a post', function () {
    $admin = actingAsAdmin();
    Storage::fake('public');

    $this->postJson('/api/v1/admin/media', [
        'type' => 'blog',
        'slug' => 'shared-cover',
        'usage' => 'cover',
        'file' => fakeImageUpload('cover.jpg'),
    ])->assertCreated();

    $cover = Media::query()->first();

    $postOne = Post::factory()->create(['slug' => 'shared-cover', 'author_id' => $admin->id, 'cover_image_id' => $cover->id]);
    $postTwo = Post::factory()->draft()->create(['slug' => 'other-post', 'author_id' => $admin->id, 'cover_image_id' => $cover->id]);

    $this->deleteJson("/api/v1/admin/posts/{$postOne->id}")->assertOk();

    expect(Media::find($cover->id))->not->toBeNull()
        ->and(Storage::disk('public')->exists($cover->path))->toBeTrue()
        ->and($postTwo->fresh()->cover_image_id)->toBe($cover->id);
});

it('deletes the previous cover when it is replaced and unreferenced', function () {
    $admin = actingAsAdmin();
    Storage::fake('public');

    $this->postJson('/api/v1/admin/media', [
        'type' => 'blog',
        'slug' => 'covers',
        'usage' => 'cover',
        'file' => fakeImageUpload('old.jpg'),
    ])->assertCreated();

    $this->postJson('/api/v1/admin/media', [
        'type' => 'blog',
        'slug' => 'covers',
        'usage' => 'cover',
        'file' => fakeImageUpload('new.jpg'),
    ])->assertCreated();

    $oldCover = Media::where('path', 'like', '%cover-%')->orderBy('id')->first();
    $newCover = Media::where('path', 'like', '%cover-%')->orderBy('id')->get()->last();

    $post = Post::factory()->create(['slug' => 'covers', 'author_id' => $admin->id, 'cover_image_id' => $oldCover->id]);

    $this->putJson("/api/v1/admin/posts/{$post->id}", ['cover_image_id' => $newCover->id])
        ->assertOk()
        ->assertJsonPath('data.cover_image_id', $newCover->id);

    expect(Media::find($oldCover->id))->toBeNull()
        ->and(Storage::disk('public')->exists($oldCover->path))->toBeFalse()
        ->and(Media::find($newCover->id))->not->toBeNull();
});

it('keeps the old cover when it is still embedded in the post content', function () {
    $admin = actingAsAdmin();
    Storage::fake('public');

    $this->postJson('/api/v1/admin/media', [
        'type' => 'blog',
        'slug' => 'covers',
        'usage' => 'cover',
        'file' => fakeImageUpload('old.jpg'),
    ])->assertCreated();

    $this->postJson('/api/v1/admin/media', [
        'type' => 'blog',
        'slug' => 'covers',
        'usage' => 'cover',
        'file' => fakeImageUpload('new.jpg'),
    ])->assertCreated();

    $oldCover = Media::where('path', 'like', '%cover-%')->orderBy('id')->first();
    $newCover = Media::where('path', 'like', '%cover-%')->orderBy('id')->get()->last();

    $post = Post::factory()->create([
        'slug' => 'covers',
        'author_id' => $admin->id,
        'cover_image_id' => $oldCover->id,
        'content' => '<p><img src="'.$oldCover->url.'"></p>',
    ]);

    $this->putJson("/api/v1/admin/posts/{$post->id}", ['cover_image_id' => $newCover->id])
        ->assertOk();

    expect(Media::find($oldCover->id))->not->toBeNull()
        ->and(Storage::disk('public')->exists($oldCover->path))->toBeTrue();
});

it('removes the event folder when an event is deleted', function () {
    actingAsAdmin();
    Storage::fake('public');

    $this->postJson('/api/v1/admin/media', [
        'type' => 'event',
        'slug' => 'donor-ball',
        'usage' => 'cover',
        'file' => fakeImageUpload('poster.jpg'),
    ])->assertCreated();

    $featured = Media::query()->first();

    $event = Event::factory()->create([
        'slug' => 'donor-ball',
        'featured_image_id' => $featured->id,
    ]);

    $this->deleteJson("/api/v1/admin/events/{$event->id}")->assertOk();

    expect(Storage::disk('public')->directoryMissing('events/donor-ball'))->toBeTrue()
        ->and(Media::find($featured->id))->toBeNull();
});

it('serves uploaded files through the storage fallback route', function () {
    Storage::fake('public');

    Storage::disk('public')->put('blogs/annual-report/cover-abc.jpg', 'image-bytes');

    $this->get('/storage/blogs/annual-report/cover-abc.jpg')
        ->assertOk()
        ->assertHeader('Content-Type', 'image/jpeg')
        ->assertHeader('Content-Length', '11');
});

it('rejects unsafe or unknown storage paths through the fallback route', function () {
    Storage::fake('public');

    Storage::disk('public')->put('blogs/annual-report/real.jpg', 'image-bytes');

    $this->get('/storage/../.env')->assertNotFound();
    $this->get('/storage/assets/logo.jpg')->assertNotFound();
    $this->get('/storage/blogs/annual-report/missing.jpg')->assertNotFound();
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
