<?php

namespace App\Providers;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->ensureStorageLink();
    }

    /**
     * Re-create the public/storage symlink when it is missing or broken.
     *
     * Shared hosts (Hostinger, etc.) often receive a copy of the app where the
     * link either never existed or came from another machine as an absolute
     * path. A fresh relative link keeps uploaded images servable on the live
     * site without needing to run `storage:link` by hand.
     */
    private function ensureStorageLink(): void
    {
        $link = public_path('storage');
        $target = storage_path('app/public');

        try {
            // Healthy link already in place – nothing to do.
            if (is_link($link) && realpath($link) !== false) {
                return;
            }

            // A regular file or directory occupies the link path – leave it alone.
            if (is_file($link) || is_dir($link)) {
                return;
            }

            if (is_link($link)) {
                @unlink($link);
            }

            $filesystem = app(Filesystem::class);

            $filesystem->ensureDirectoryExists(dirname($link));
            $filesystem->relativeLink($target, $link);
        } catch (\Throwable) {
            // Symbolic links are sometimes disabled on shared hosts. Symfony-style
            // requests then fall through to Laravel's built-in /storage route,
            // which serves the public disk because it is marked as `serveable`.
        }
    }
}
