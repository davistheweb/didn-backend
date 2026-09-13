<?php

namespace App\Services;

use App\Models\Media;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class MediaService
{
    /**
     * Persist an uploaded file to the configured disk and record it.
     */
    public function store(UploadedFile $file, string $disk = 'public'): Media
    {
        $directory = 'backend/uploads/'.now()->format('Y/m');

        $path = $file->store($directory, $disk);

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
}
