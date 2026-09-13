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
            'file' => [
                'required',
                'file',
                'image',
                'mimes:jpeg,png,webp,gif,avif',
                'max:5120',
            ],
        ];
    }

    public function messages(): array
    {
        $limit = ini_get('upload_max_filesize');

        return [
            'file.file' => 'The file failed to upload. The server upload limit is currently '.$limit.', raise upload_max_filesize and post_max_size in php.ini (or .user.ini) before retrying.',
            'file.image' => 'The uploaded file must be an image.',
            'file.mimes' => 'Images must be one of: jpeg, png, webp, gif, avif.',
            'file.max' => 'Images must not be larger than 5MB.',
        ];
    }
}
