<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\Concerns\RejectsUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class RegisterRequest extends FormRequest
{
    use RejectsUnknownFields;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'studentNumber' => is_string($this->input('studentNumber')) ? trim($this->input('studentNumber')) : $this->input('studentNumber'),
            'fullName' => is_string($this->input('fullName')) ? trim($this->input('fullName')) : $this->input('fullName'),
            'email' => is_string($this->input('email')) ? mb_strtolower(trim($this->input('email'))) : $this->input('email'),
        ]);
    }

    public function rules(): array
    {
        return [
            'studentNumber' => ['required', 'string', 'min:1', 'max:40'],
            'fullName' => ['required', 'string', 'min:2', 'max:160'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'password' => ['required', 'string', 'min:12', 'max:128'],
            'program' => ['sometimes', 'nullable', 'string', 'max:160'],
            'yearLevel' => ['sometimes', 'integer', 'min:1', 'max:10'],
            'section' => ['sometimes', 'nullable', 'string', 'max:80'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $this->rejectUnknownFields($validator, array_keys($this->rules()));
        }];
    }
}
