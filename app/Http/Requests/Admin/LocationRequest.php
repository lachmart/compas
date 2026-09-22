<?php

namespace App\Http\Requests\Admin;

use App\Enums\TaskType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'arrival_radius_m' => ['required', 'integer', 'min:5', 'max:200'],
            'task_type' => ['required', Rule::enum(TaskType::class)],
        ];

        return match ($this->input('task_type')) {
            TaskType::PickImage->value => $rules + [
                'images' => ['required', 'array', 'min:3', 'max:6'],
                'images.*' => ['image', 'max:5120'],
                'correct_index' => ['required', 'integer', 'min:0', function ($attribute, $value, $fail) {
                    $count = count($this->file('images', []));
                    if ($value >= $count) {
                        $fail('correct_index must reference one of the uploaded images.');
                    }
                }],
            ],
            TaskType::PhotoOfSomething->value, TaskType::SelfieDoingSomething->value => $rules + [
                'reference_image' => ['required', 'image', 'max:5120'],
            ],
            default => $rules,
        };
    }
}
