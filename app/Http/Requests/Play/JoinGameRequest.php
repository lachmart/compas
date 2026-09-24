<?php

namespace App\Http\Requests\Play;

use App\Enums\Avatar;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class JoinGameRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'avatar' => [
                'required',
                Rule::enum(Avatar::class),
                // Another child in the game already has this animal.
                Rule::notIn($this->route('game')->takenAvatars()),
            ],
            'nickname' => ['nullable', 'string', 'max:50'],
        ];
    }
}
