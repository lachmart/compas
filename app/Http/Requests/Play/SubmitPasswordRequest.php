<?php

namespace App\Http\Requests\Play;

use Illuminate\Foundation\Http\FormRequest;

class SubmitPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'answer' => ['required', 'string', 'max:200'],
        ];
    }
}
