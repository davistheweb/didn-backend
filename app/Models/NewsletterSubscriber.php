<?php

namespace App\Models;

use Database\Factories\NewsletterSubscriberFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

#[Fillable([
    'email',
    'status',
    'unsubscribe_token',
    'subscribed_at',
    'unsubscribed_at',
])]
class NewsletterSubscriber extends Model
{
    /** @use HasFactory<NewsletterSubscriberFactory> */
    use HasFactory;

    public const STATUS_SUBSCRIBED = 'subscribed';

    public const STATUS_UNSUBSCRIBED = 'unsubscribed';

    public const STATUSES = [self::STATUS_SUBSCRIBED, self::STATUS_UNSUBSCRIBED];

    protected static function booted(): void
    {
        static::creating(function (NewsletterSubscriber $subscriber) {
            if ($subscriber->unsubscribe_token === null) {
                $subscriber->unsubscribe_token = Str::random(64);
            }
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'subscribed_at' => 'datetime',
            'unsubscribed_at' => 'datetime',
        ];
    }

    public function isSubscribed(): bool
    {
        return $this->status === self::STATUS_SUBSCRIBED;
    }

    /**
     * Re-activate a former subscriber with a fresh unsubscribe token.
     */
    public function subscribe(): void
    {
        $this->status = self::STATUS_SUBSCRIBED;
        $this->subscribed_at = now();
        $this->unsubscribed_at = null;
        $this->unsubscribe_token = Str::random(64);
        $this->save();
    }

    /**
     * Deactivate the subscriber without deleting the record.
     */
    public function unsubscribe(): void
    {
        if ($this->isSubscribed()) {
            $this->status = self::STATUS_UNSUBSCRIBED;
            $this->unsubscribed_at = now();
            $this->save();
        }
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_SUBSCRIBED);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        return $query->when($term, static function (Builder $query, string $term): void {
            $query->where('email', 'like', "%{$term}%");
        });
    }
}
