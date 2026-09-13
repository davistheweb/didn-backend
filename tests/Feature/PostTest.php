<?php

use App\Models\Post;
use App\Models\User;

it('denies anonymous access to admin post endpoints', function () {
    $this->getJson('/api/v1/admin/posts')->assertStatus(401);
    $this->postJson('/api/v1/admin/posts')->assertStatus(401);
});

it('creates a published post with author and published_at', function () {
    $admin = actingAsAdmin();

    $response = $this->postJson('/api/v1/admin/posts', [
        'title' => 'Building Stronger Communities',
        'category' => 'Legal Services',
        'excerpt' => 'An excerpt.',
        'content' => '<h2>Intro</h2><p>Body text.</p>',
        'status' => 'published',
    ])
        ->assertStatus(201)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.slug', 'building-stronger-communities')
        ->assertJsonPath('data.author.id', $admin->id)
        ->assertJsonPath('data.author.name', $admin->name);

    expect($response->json('data.published_at'))->not->toBeNull();

    $this->assertDatabaseHas('posts', [
        'slug' => 'building-stronger-communities',
        'author_id' => $admin->id,
        'status' => 'published',
    ]);
});

it('creates a draft post without publishing it', function () {
    actingAsAdmin();

    $this->postJson('/api/v1/admin/posts', [
        'title' => 'Work in Progress',
        'content' => '<p>Draft text.</p>',
        'status' => 'draft',
    ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.published_at', null);
});

it('allows creating a post without content and reports it as empty', function () {
    $admin = actingAsAdmin();

    $response = $this->postJson('/api/v1/admin/posts', [
        'title' => 'Blog Draft',
        'status' => 'draft',
    ])->assertCreated();

    expect($response->json('data.content'))->toBeNull()
        ->and($response->json('data.has_content'))->toBeFalse()
        ->and($response->json('data.author.id'))->toBe($admin->id);
});

it('validates the post payload', function () {
    actingAsAdmin();

    $this->postJson('/api/v1/admin/posts', [
        'title' => '',
        'status' => 'archived',
    ])
        ->assertStatus(422)
        ->assertJsonPath('success', false)
        ->assertJsonStructure(['errors' => ['title', 'status']]);
});

it('publishes a draft and unpublishes a published post', function () {
    actingAsAdmin();
    $post = Post::factory()->draft()->create();

    $this->putJson("/api/v1/admin/posts/{$post->id}", ['status' => 'published'])
        ->assertOk()
        ->assertJsonPath('data.status', 'published');

    expect($post->fresh()->published_at)->not->toBeNull();

    $post->refresh();

    $this->putJson("/api/v1/admin/posts/{$post->id}", ['status' => 'draft'])
        ->assertOk()
        ->assertJsonPath('data.status', 'draft');
});

it('updates a post keeping the original author', function () {
    $admin = actingAsAdmin();
    $post = Post::factory()->create(['author_id' => $admin->id]);

    $this->putJson("/api/v1/admin/posts/{$post->id}", [
        'title' => 'Updated Title',
        'content' => '<p>Updated.</p>',
    ])
        ->assertOk()
        ->assertJsonPath('data.title', 'Updated Title')
        ->assertJsonPath('data.author.id', $admin->id);
});

it('deletes a post', function () {
    actingAsAdmin();
    $post = Post::factory()->create();

    $this->deleteJson("/api/v1/admin/posts/{$post->id}")
        ->assertOk()
        ->assertJsonPath('success', true);

    $this->assertDatabaseMissing('posts', ['id' => $post->id]);
});

it('only exposes published posts on the public index', function () {
    actingAsAdmin();
    Post::factory()->published()->create(['title' => 'Public Article']);
    Post::factory()->draft()->create(['title' => 'Private Draft']);

    $this->getJson('/api/v1/posts')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.title', 'Public Article');
});

it('filters public posts by category and search', function () {
    actingAsAdmin();
    Post::factory()->published()->create([
        'title' => 'Tech for Crime Prevention',
        'category' => 'Legal Services',
    ]);
    Post::factory()->published()->create([
        'title' => 'Climate Action in Bayelsa',
        'category' => 'Research',
    ]);

    $this->getJson('/api/v1/posts?category=Legal%20Services')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.category', 'Legal Services');

    $this->getJson('/api/v1/posts?search=climate')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.title', 'Climate Action in Bayelsa');
});

it('requires published status to view a post by slug', function () {
    actingAsAdmin();
    $draft = Post::factory()->draft()->create(['slug' => 'hidden-story']);
    $published = Post::factory()->published()->create(['slug' => 'visible-story']);

    $this->getJson('/api/v1/posts/visible-story')->assertOk()->assertJsonPath('data.slug', 'visible-story');
    $this->getJson('/api/v1/posts/hidden-story')->assertNotFound()->assertJsonPath('success', false);
    $this->getJson('/api/v1/posts/nope')->assertNotFound()->assertJsonPath('success', false);
});

it('exposes a stable post resource shape', function () {
    actingAsAdmin();
    $author = User::factory()->create(['name' => 'Amina Yusuf']);
    Post::factory()->published()->create([
        'title' => 'Resource Shape Test',
        'category' => 'Research',
        'author_id' => $author->id,
        'published_at' => now(),
    ]);

    $this->getJson('/api/v1/posts')
        ->assertOk()
        ->assertJsonPath('data.0.date', now()->format('F j, Y'))
        ->assertJsonStructure([
            'data' => [[
                'id', 'title', 'slug', 'category', 'excerpt', 'content',
                'image', 'date', 'published_at', 'status',
                'author' => ['id', 'name'],
            ]],
        ]);
});
