<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Validation\Validator;

trait RejectsUnknownFields
{
    /** @param list<string> $allowed */
    protected function rejectUnknownFields(Validator $validator, array $allowed): void
    {
        foreach (array_diff(array_keys($this->all()), $allowed) as $field) {
            $validator->errors()->add((string) $field, 'This field is not allowed.');
        }
    }
}
