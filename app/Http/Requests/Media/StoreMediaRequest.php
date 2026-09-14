<?php

namespace App\Http\Requests\Media;

use Illuminate\Foundation\Http\FormRequest;

class StoreMediaRequest extends FormRequest
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
            'type' => [
                'required',
                'string',
                'in:blog,event',
            ],
            'slug' => [
                'nullable',
                'string',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
            ],
            'usage' => [
                'nullable',
                'string',
                'in:cover,content',
            ],
            'file' => [
                'required',
                'file',
                'image',
                'mimes:jpeg,jpg,png,webp,gif',
                'max:5120',
            ],
        ];
    }

    public function messages(): array
    {
        $limit = ini_get('upload_max_filesize');

        return [
            'type.required' => 'The media type is required.',
            'type.in' => 'The media type must be one of: blog, event.',
            'slug.regex' => 'The slug may only contain lowercase letters, numbers, and hyphens between words.',
            'usage.in' => 'The usage must be either cover or content.',
            'file.file' => 'The file failed to upload. The server upload limit is currently '.$limit.', raise upload_max_filesize and post_max_size in php.ini (or .user.ini) before retrying.',
            'file.image' => 'The uploaded file must be an image.',
            'file.mimes' => 'Images must be one of: jpeg, png, webp, gif.',
            'file.max' => 'Images must not be larger than 5MB.',
        ];
    }
}
