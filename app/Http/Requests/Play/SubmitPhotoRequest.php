<?php

namespace App\Http\Requests\Play;

use Illuminate\Foundation\Http\FormRequest;

class SubmitPhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'photo' => ['required', 'image', 'max:5120'],
        ];
    }
}
