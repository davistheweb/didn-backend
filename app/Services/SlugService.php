<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SlugService
{
    /**
     * Generate a URL-safe, unique slug for the given value.
     *
     * @param  class-string<Model>  $modelClass
     */
    public function unique(string $value, string $modelClass, ?int $ignoreId = null, string $column = 'slug'): string
    {
        $base = Str::slug($value);

        if ($base === '') {
            $base = Str::slug(class_basename($modelClass)).'-'.Str::lower(Str::random(6));
        }

        $slug = $base;
        $suffix = 2;

        while ($modelClass::query()
            ->where($column, $slug)
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
