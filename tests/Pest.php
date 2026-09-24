<?php

use App\Models\User;
use App\Services\Email\ResendEmailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

function makeAdmin(array $attributes = []): User
{
    return User::factory()->create(array_merge([
        'password' => 'password',
    ], $attributes));
}

function actingAsAdmin(array $attributes = []): User
{
    $admin = makeAdmin($attributes);
    Sanctum::actingAs($admin);

    return $admin;
}

/**
 * Bind a Resend spy that records sends without touching the network.
 *
 * The real unsubscribeUrl() implementation is kept so jobs and services can
 * build links. Inspect recorded sends via $spy->sent.
 */
function fakeResendEmailService(): ResendEmailService
{
    $spy = new class extends ResendEmailService
    {
        public array $sent = [];

        public function send(array $to, string $subject, string $html, ?string $from = null, ?array $replyTo = null): void
        {
            $from ??= config('services.resend.from_address');
            $this->sent[] = compact('to', 'subject', 'html', 'from', 'replyTo');
        }
    };

    app()->instance(ResendEmailService::class, $spy);

    return $spy;
}

/**
 * Build a real, minimal JPEG upload without requiring the GD extension.
 */
function fakeImageUpload(string $name = 'cover.jpg'): UploadedFile
{
    $bytes = base64_decode(
        '/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0aHBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/wAALCAABAAEBAREA/8QAFAABAAAAAAAAAAAAAAAAAAAACf/EABQQAQAAAAAAAAAAAAAAAAAAAAD/2gAIAQEAAD8AVN//2Q==',
    );

    $path = tempnam(sys_get_temp_dir(), 'didn').'.jpg';
    file_put_contents($path, $bytes);

    return new UploadedFile($path, $name, 'image/jpeg', null, true);
}
