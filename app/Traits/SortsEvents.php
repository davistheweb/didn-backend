<?php

namespace App\Traits;

use App\Models\Event;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

trait SortsEvents
{
    /**
     * Apply a deterministic, validated sort to an Event query.
     */
    protected function applyEventSort(Builder $query, Request $request, ?string $status): void
    {
        $allowed = ['start_date', 'end_date', 'created_at', 'title'];
        $sortBy = in_array($request->input('sort_by'), $allowed, true)
            ? $request->input('sort_by')
            : 'start_date';

        $sortDir = strtolower((string) $request->input('sort_dir')) === 'desc' ? 'desc' : 'asc';

        if (! $request->has('sort_by')) {
            $sortDir = $status === Event::STATUS_UPCOMING ? 'asc' : 'desc';
        }

        $query->orderBy($sortBy, $sortDir);
    }
}
