<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'title',
    'slug',
    'description',
    'content',
    'event_type',
    'location',
    'start_date',
    'end_date',
    'featured_image_id',
    'is_published',
])]
class Event extends Model
{
    use HasFactory;

    public const TYPES = ['conference', 'campaign', 'training', 'webinar'];

    public const STATUS_UPCOMING = 'upcoming';

    public const STATUS_PAST = 'past';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'datetime',
            'end_date' => 'datetime',
            'is_published' => 'boolean',
        ];
    }

    /**
     * Derive whether the event is upcoming or has already ended.
     *
     * The event is considered past when its effective end date (end_date,
     * falling back to start_date) is in the past; otherwise it is upcoming.
     */
    public function getStatusAttribute(): string
    {
        $endsAt = $this->end_date ?? $this->start_date;

        return $endsAt->lt(now()) ? 'past' : 'upcoming';
    }

    public function featuredImage(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'featured_image_id');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public function scopeOfType(Builder $query, ?string $type): Builder
    {
        return $query->when($type, fn (Builder $q) => $q->where('event_type', $type));
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->where('end_date', '>=', now())
                ->orWhere(function (Builder $q2) {
                    $q2->whereNull('end_date')->where('start_date', '>=', now());
                });
        });
    }

    public function scopePast(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->where('end_date', '<', now())
                ->orWhere(function (Builder $q2) {
                    $q2->whereNull('end_date')->where('start_date', '<', now());
                });
        });
    }
}
