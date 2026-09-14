<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Media;
use App\Models\Post;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class MediaService
{
    public const CONTEXT_BLOG = 'blog';

    public const CONTEXT_EVENT = 'event';

    public const USAGE_COVER = 'cover';

    public const USAGE_CONTENT = 'content';

    /**
     * Map a media context to the folder that owns its content.
     */
    public function contextFolder(string $context): string
    {
        return $context === self::CONTEXT_BLOG ? 'blogs' : 'events';
    }

    /**
     * Persist an uploaded file into the record's content folder and record it.
     *
     * Files are stored under {context}/{slug}/ when a slug is known, otherwise
     * under {context}/_pending/{uuid}/ so they can be reconciled once the
     * record is saved with a real slug.
     */
    public function store(
        UploadedFile $file,
        string $context = self::CONTEXT_BLOG,
        ?string $slug = null,
        string $usage = self::USAGE_CONTENT,
        string $disk = 'public',
    ): Media {
        $folder = $this->contextFolder($context);
        $directory = $folder.'/'.($slug ?: '_pending/'.Str::uuid());

        $prefix = $usage === self::USAGE_COVER ? 'cover' : 'image';
        $extension = $file->guessExtension() ?: 'jpg';
        $name = sprintf('%s-%s.%s', $prefix, Str::lower(Str::random(8)), $extension);

        $path = $file->storeAs($directory, $name, $disk);

        if ($path === false) {
            throw new RuntimeException('The file could not be written to storage. Make sure the storage/app/public directory is writable.');
        }

        return Media::create([
            'disk' => $disk,
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
        ]);
    }

    /**
     * Detach the media from content, remove its file, then delete the record.
     */
    public function delete(Media $media): void
    {
        $media->featuredEvents()->update(['featured_image_id' => null]);
        $media->coverPosts()->update(['cover_image_id' => null]);

        Storage::disk($media->disk)->delete($media->path);

        $media->delete();
    }

    /**
     * Ensure the record's media lives under its content-slug folder.
     *
     * The cover/featured media folder and every pending upload folder
     * referenced inside the content HTML are reconciled together. Media files,
     * their database rows, and the stored content URLs are moved as a unit.
     */
    public function reconcileSlugFolder(string $context, Event|Post $record, string $disk = 'public'): void
    {
        $folder = $this->contextFolder($context);
        $target = $folder.'/'.$record->slug;

        $sources = [];

        $cover = $context === self::CONTEXT_BLOG ? $record->coverImage : $record->featuredImage;

        if ($cover !== null) {
            $coverDir = $this->directoryOfMedia($cover, $context);
            if ($coverDir !== null) {
                $sources[] = $coverDir;
            }
        }

        if (filled($record->content)) {
            $sources = array_merge($sources, $this->directoriesFromHtml($record->content, $context));
        }

        $sources = array_values(array_unique(array_filter(
            $sources,
            static fn (string $dir): bool => $dir !== $target,
        )));

        foreach ($sources as $source) {
            $this->moveDirectory($source, $target, $disk);

            Media::query()
                ->where('disk', $disk)
                ->where('path', 'like', $source.'/%')
                ->get()
                ->each(function (Media $media) use ($source, $target) {
                    $media->path = $target.'/'.substr($media->path, strlen($source) + 1);
                    $media->save();
                });

            if (filled($record->content)) {
                $record->content = $this->rewriteUrls($record->content, $source, $target);
                $record->save();
            }
        }
    }

    /**
     * Remove the folder that belonged to a deleted record.
     *
     * Media rows that are still referenced by other records (or the cover of
     * the record being deleted) are kept; the folder itself is only removed
     * once no tracked media remains inside it.
     */
    public function deleteContentDirectory(string $context, Event|Post $record, string $disk = 'public'): void
    {
        $folder = $this->contextFolder($context).'/'.$record->slug;

        Media::query()
            ->where('disk', $disk)
            ->where('path', 'like', $folder.'/%')
            ->get()
            ->each(function (Media $media) use ($record) {
                if (! $this->isReferencedElsewhere($media, $record, false)) {
                    $this->delete($media);
                }
            });

        $stillReferenced = Media::query()
            ->where('disk', $disk)
            ->where('path', 'like', $folder.'/%')
            ->exists();

        if (! $stillReferenced) {
            Storage::disk($disk)->deleteDirectory($folder);
        }
    }

    /**
     * Delete the previous cover once nothing references it anymore.
     *
     * Content that still embeds the image keeps the media alive.
     */
    public function deleteCoverIfUnreferenced(?Media $media, Event|Post $record): void
    {
        if ($media === null || $this->isReferencedElsewhere($media, $record)) {
            return;
        }

        $this->delete($media);
    }

    /**
     * Whether media is still referenced by another record or embedded content.
     */
    public function isReferencedElsewhere(Media $media, Event|Post $record, bool $includeRecordContent = true): bool
    {
        $eventQuery = Event::query()->where('featured_image_id', $media->id);

        if ($record instanceof Event) {
            $eventQuery->whereKeyNot($record->getKey());
        }

        if ($eventQuery->exists()) {
            return true;
        }

        $postQuery = Post::query()->where('cover_image_id', $media->id);

        if ($record instanceof Post) {
            $postQuery->whereKeyNot($record->getKey());
        }

        if ($postQuery->exists()) {
            return true;
        }

        if ($includeRecordContent && filled($record->content) && str_contains($record->content, '/storage/'.$media->path)) {
            return true;
        }

        return false;
    }

    /**
     * The content folder a media file lives in, or null when it is outside
     * the given context (e.g. legacy uploads or the backup faker).
     */
    public function directoryOfMedia(Media $media, string $context): ?string
    {
        $folder = $this->contextFolder($context).'/';

        if (! str_starts_with($media->path, $folder)) {
            return null;
        }

        $directory = dirname($media->path);

        return $directory === rtrim($folder, '/') ? null : $directory;
    }

    /**
     * Find every pending upload folder referenced inside content HTML.
     *
     * Only pending folders qualify: once media lives under a real slug folder
     * it is owned by that record and must not be moved by another.
     *
     * @return list<string>
     */
    public function directoriesFromHtml(string $html, string $context): array
    {
        if (preg_match_all('/<img[^>]+src=["\']([^"\']+)["\']/i', $html, $matches) === 0) {
            return [];
        }

        $folder = $this->contextFolder($context);
        $directories = [];

        foreach ($matches[1] as $src) {
            $needle = '/storage/'.$folder.'/_pending/';
            $offset = strpos($src, $needle);

            if ($offset === false) {
                continue;
            }

            $relative = substr($src, $offset + strlen('/storage/'));
            $directory = dirname($relative);

            if ($directory !== $folder.'/_pending' && str_starts_with($directory, $folder.'/_pending/')) {
                $directories[] = $directory;
            }
        }

        return array_values(array_unique($directories));
    }

    /**
     * Move every file from one directory into another, merging into an
     * existing target instead of requiring it to be absent.
     */
    public function moveDirectory(string $fromDir, string $toDir, string $disk = 'public'): void
    {
        $storage = Storage::disk($disk);

        if ($fromDir === $toDir || $storage->directoryMissing($fromDir)) {
            return;
        }

        $storage->makeDirectory($toDir);

        foreach ($storage->allFiles($fromDir) as $file) {
            $destination = $toDir.'/'.basename($file);

            if (! $storage->exists($destination)) {
                $storage->move($file, $destination);
            }
        }

        $storage->deleteDirectory($fromDir);
    }

    public function rewriteUrls(string $content, string $fromDir, string $toDir): string
    {
        return str_replace('/storage/'.$fromDir.'/', '/storage/'.$toDir.'/', $content);
    }
}
