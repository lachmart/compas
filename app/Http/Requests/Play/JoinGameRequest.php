<?php

namespace App\Http\Requests\Play;

use Illuminate\Foundation\Http\FormRequest;

class JoinGameRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nickname' => ['required', 'string', 'max:50'],
        ];
    }
}
