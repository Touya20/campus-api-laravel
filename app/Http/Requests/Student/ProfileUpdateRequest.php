<?php

namespace App\Http\Requests\Student;

use App\Http\Requests\Concerns\RejectsUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class ProfileUpdateRequest extends FormRequest
{
    use RejectsUnknownFields;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fullName' => ['sometimes', 'string', 'min:2', 'max:160'],
            'program' => ['sometimes', 'nullable', 'string', 'max:160'],
            'yearLevel' => ['sometimes', 'nullable', 'string', 'max:40'],
            'section' => ['sometimes', 'nullable', 'string', 'max:80'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $this->rejectUnknownFields($validator, array_keys($this->rules()));
            if ($this->all() === []) {
                $validator->errors()->add('profile', 'Provide at least one editable profile field.');
            }
        }];
    }
}
