<?php

namespace App\Http\Requests\Admin;

use App\Enums\TaskType;
use App\Models\Location;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class LocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'name' => ['nullable', 'string', 'max:100'],
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'arrival_radius_m' => ['required', 'integer', 'min:5', 'max:200'],
            'task_type' => ['required', Rule::enum(TaskType::class)],
        ];

        return match ($this->input('task_type')) {
            TaskType::PickImage->value => $rules + [
                'existing_images' => ['sometimes', 'array'],
                // Only images currently attached to THIS location may be kept.
                'existing_images.*' => ['string', 'distinct', Rule::in($this->currentLocation()?->images() ?? [])],
                'images' => ['sometimes', 'array'],
                'images.*' => ['image', 'max:5120'],
                'correct_index' => ['required', 'integer', 'min:0', function ($attribute, $value, $fail) {
                    if ($value >= $this->totalImageCount()) {
                        $fail('Vyber správný obrázek z nahraných obrázků.');
                    }
                }],
            ],
            TaskType::PhotoOfSomething->value, TaskType::SelfieDoingSomething->value => $rules + [
                'reference_image' => [
                    $this->currentLocation()?->referenceImage() ? 'nullable' : 'required',
                    'image',
                    'max:5120',
                ],
            ],
            default => $rules,
        };
    }

    public function messages(): array
    {
        return [
            'correct_index.required' => 'Označ správný obrázek.',
            'reference_image.required' => 'Nahraj referenční obrázek.',
            'images.*.image' => 'Soubor musí být obrázek.',
            'reference_image.image' => 'Soubor musí být obrázek.',
            'images.*.max' => 'Obrázek může mít nejvýš 5 MB.',
            'reference_image.max' => 'Obrázek může mít nejvýš 5 MB.',
            'existing_images.*.in' => 'Stávající obrázek už u stanoviště není — obnov stránku.',
            'existing_images.*.distinct' => 'Obrázek je vybraný víckrát.',
            'name.max' => 'Název může mít nejvýš 100 znaků.',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($this->input('task_type') !== TaskType::PickImage->value) {
                    return;
                }

                $total = $this->totalImageCount();

                if ($total < 3 || $total > 6) {
                    $validator->errors()->add('images', 'Úkol musí mít 3 až 6 obrázků.');
                }
            },
        ];
    }

    private function currentLocation(): ?Location
    {
        return $this->route('location');
    }

    private function totalImageCount(): int
    {
        return count((array) $this->input('existing_images', [])) + count($this->file('images', []));
    }
}
