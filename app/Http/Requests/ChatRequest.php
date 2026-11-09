<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\RejectsUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class ChatRequest extends FormRequest
{
    use RejectsUnknownFields;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'messages' => ['required', 'array', 'min:1', 'max:6'],
            'messages.*' => ['required', 'array'],
            'messages.*.role' => ['required', 'string', 'in:user,assistant'],
            'messages.*.content' => ['required', 'string', 'min:1', 'max:6000'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $this->rejectUnknownFields($validator, ['messages']);

            foreach ($this->input('messages', []) as $index => $message) {
                if (is_array($message)) {
                    foreach (array_diff(array_keys($message), ['role', 'content']) as $key) {
                        $validator->errors()->add("messages.{$index}.{$key}", 'This field is not allowed.');
                    }

                    if (($message['role'] ?? null) === 'user'
                        && is_string($message['content'] ?? null)
                        && mb_strlen($message['content']) > 1000) {
                        $validator->errors()->add("messages.{$index}.content", 'User messages may not exceed 1000 characters.');
                    }
                }
            }

            $messages = $this->input('messages', []);
            if (is_array($messages) && $messages !== []) {
                $lastMessage = end($messages);
                if (is_array($lastMessage) && ($lastMessage['role'] ?? null) !== 'user') {
                    $validator->errors()->add('messages', 'The last message must be from the user.');
                }
            }
        }];
    }
}
