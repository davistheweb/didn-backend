<?php

namespace App\Http\Requests\Post;

use App\Models\Post;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'category' => ['sometimes', 'nullable', 'string', 'max:100'],
            'excerpt' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'content' => ['sometimes', 'nullable', 'string'],
            'cover_image_id' => ['sometimes', 'nullable', 'integer', 'exists:media,id'],
            'status' => ['sometimes', 'required', 'string', 'in:'.implode(',', Post::STATUSES)],
            'published_at' => ['sometimes', 'nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'slug.regex' => 'The slug may only contain lowercase letters, numbers, and hyphens.',
        ];
    }
}
