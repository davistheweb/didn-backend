<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AdminSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the primary admin account from environment variables.
     *
     * Expected variables:
     *   ADMIN_NAME      (defaults to "DIDN Admin")
     *   ADMIN_EMAIL     (development default: admin@didn.org)
     *   ADMIN_PASSWORD  (development default: didn-dev-password)
     *
     * The development defaults are intentionally weak and only for local
     * use. In production, ADMIN_EMAIL and ADMIN_PASSWORD MUST be provided.
     */
    public function run(): void
    {
        $email = env('ADMIN_EMAIL');
        $password = env('ADMIN_PASSWORD');

        if ($email === null || $email === '') {
            if (app()->environment('production')) {
                throw new \RuntimeException(
                    'DatabaseSeeder requires the ADMIN_EMAIL environment variable in production.',
                );
            }

            $email = 'admin@didn.org';
            $password = Str::length(trim((string) $password)) > 0 ? $password : 'didn-dev-password';

            Log::warning('AdminSeeder used development defaults. Set ADMIN_EMAIL/ADMIN_PASSWORD in your .env.');
        }

        if (Str::length(trim((string) $password)) < 8) {
            throw new \RuntimeException('ADMIN_PASSWORD must be at least 8 characters.');
        }

        User::updateOrCreate(
            ['email' => $email],
            [
                'name' => env('ADMIN_NAME', 'DIDN Admin'),
                'password' => $password,
            ],
        );

        $this->command?->info("Admin user ensured: {$email}");
    }
}
