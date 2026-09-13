<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DevContentSeeder extends Seeder
{
    /**
     * Create clearly-marked demo content for local development and testing.
     *
     * Every record is prefixed with "[Demo]" so it can never be confused
     * with real DIDN content. Content is only seeded outside production.
     */
    public function run(): void
    {
        if (app()->environment('production')) {
            return;
        }

        $author = User::where('email', env('ADMIN_EMAIL', 'admin@didn.org'))->first()
            ?? User::query()->first();

        if ($author === null) {
            $this->command?->warn('No admin user exists yet; run AdminSeeder first.');

            return;
        }

        $this->seedEvents();
        $this->seedPosts($author);

        $this->command?->info('Demo events and posts seeded.');
    }

    private function seedEvents(): void
    {
        $events = [
            [
                'title' => '[Demo] Civil Society Roundtable on Digital Rights',
                'event_type' => 'training',
                'location' => 'Yenagoa, Bayelsa',
                'start_date' => now()->addDays(10),
                'end_date' => now()->addDays(11),
                'is_published' => true,
            ],
            [
                'title' => '[Demo] Community Safety Town Hall',
                'event_type' => 'conference',
                'location' => 'Abuja, FCT',
                'start_date' => now()->addDays(40),
                'end_date' => null,
                'is_published' => true,
            ],
            [
                'title' => '[Demo] Youth Leadership Bootcamp',
                'event_type' => 'campaign',
                'location' => 'Lagos',
                'start_date' => now()->subDays(15),
                'end_date' => now()->subDays(13),
                'is_published' => true,
            ],
            [
                'title' => '[Demo] Draft: Civic Space Grants Webinar',
                'event_type' => 'webinar',
                'location' => 'Online',
                'start_date' => now()->addDays(25),
                'end_date' => null,
                'is_published' => false,
            ],
        ];

        foreach ($events as $event) {
            $title = $event['title'];

            Event::firstOrCreate(
                ['slug' => Str::slug($title)],
                [
                    'title' => $title,
                    'description' => 'Demo description for development only. Not real DIDN content.',
                    'content' => '<h2>Demo content</h2><p>This event exists for local development and testing only.</p>',
                    'location' => $event['location'],
                    'start_date' => $event['start_date'],
                    'end_date' => $event['end_date'],
                    'event_type' => $event['event_type'],
                    'is_published' => $event['is_published'],
                ],
            );
        }
    }

    private function seedPosts(User $author): void
    {
        $posts = [
            [
                'title' => '[Demo] Leveraging Technology for Crime Prevention in Bayelsa Communities',
                'category' => 'Legal Services',
                'status' => Post::STATUS_PUBLISHED,
            ],
            [
                'title' => '[Demo] What Happened at the 3rd Africa High-Level Civil Society AML/CFT Conference?',
                'category' => 'Defending the Civic Space',
                'status' => Post::STATUS_PUBLISHED,
            ],
            [
                'title' => '[Demo] Draft: Notes from the Bayelsa Safety Workshop',
                'category' => 'Research',
                'status' => Post::STATUS_DRAFT,
            ],
        ];

        foreach ($posts as $post) {
            $title = $post['title'];

            Post::firstOrCreate(
                ['slug' => Str::slug($title)],
                [
                    'title' => $title,
                    'category' => $post['category'],
                    'excerpt' => 'Demo excerpt for development only. Not real DIDN content.',
                    'content' => '<h2>Demo content</h2><p>This article exists for local development and testing only.</p>',
                    'status' => $post['status'],
                    'author_id' => $author->id,
                ],
            );
        }
    }
}
