<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

#[Fillable(['disk', 'path', 'original_name', 'mime_type', 'size'])]
#[Hidden(['path'])]
class Media extends Model
{
    /**
     * Get the absolute public URL for the media file.
     */
    public function getUrlAttribute(): ?string
    {
        return $this->path ? Storage::disk($this->disk)->url($this->path) : null;
    }

    public function featuredEvents(): HasMany
    {
        return $this->hasMany(Event::class, 'featured_image_id');
    }

    public function coverPosts(): HasMany
    {
        return $this->hasMany(Post::class, 'cover_image_id');
    }
}
