<?php

namespace App\Http\Requests\Play;

use Illuminate\Foundation\Http\FormRequest;

class SubmitAnswerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'selected_index' => ['required', 'integer', 'min:0'],
        ];
    }
}
