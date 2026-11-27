<?php

namespace App\Http\Requests\Settings;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A client's profile photo: a JPG, PNG or WebP (read from its bytes), up to
 * config('profile.photo_max_kb'). Only a client has a profile to keep one;
 * staff photos are not taken yet.
 */
class ProfilePhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isClient() ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'photo' => ['required', 'file', 'mimes:'.implode(',', config('profile.photo_mimes')), 'max:'.config('profile.photo_max_kb')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'photo.required' => __('Choose a photo.'),
            'photo.mimes' => __('Choose a JPG, PNG or WebP photo.'),
            'photo.max' => __('The photo can be :max KB at most.'),
            'photo.uploaded' => __('The photo could not be uploaded. Try a smaller one.'),
        ];
    }
}
