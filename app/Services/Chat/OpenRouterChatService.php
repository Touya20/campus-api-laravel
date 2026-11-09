<?php

namespace App\Services\Chat;

use Illuminate\Support\Facades\Http;
use UnexpectedValueException;

final class OpenRouterChatService
{
    /** @param list<array{role: string, content: string}> $messages */
    public function reply(array $messages): string
    {
        $apiKey = (string) config('services.openrouter.api_key');
        if ($apiKey === '') {
            throw new UnexpectedValueException('OpenRouter is not configured.');
        }

        $systemMessage = [
            'role' => 'system',
            'content' => 'You are Campus Helper, a clear and supportive study assistant. Answer questions about lessons and general topics accurately. Explain ideas step by step when useful. Do not claim to know private student portal information unless it is included in the conversation. If unsure, say so.',
        ];

        $response = Http::acceptJson()
            ->withToken($apiKey)
            ->withHeaders([
                'HTTP-Referer' => (string) config('app.url'),
                'X-Title' => (string) config('app.name', 'Campus Student Portal'),
            ])
            ->timeout(35)
            ->post(rtrim((string) config('services.openrouter.base_url'), '/') . '/chat/completions', [
                'model' => (string) config('services.openrouter.model', 'openai/gpt-4o'),
                'messages' => array_merge([$systemMessage], $messages),
                // Keep reasoning-capable models from spending the whole completion budget
                // before they produce the visible answer.
                'reasoning_effort' => 'low',
                'max_completion_tokens' => 1600,
                'temperature' => 0.5,
            ])
            ->throw();

        $body = $response->json();
        $choices = is_array($body) ? ($body['choices'] ?? null) : null;
        $firstChoice = is_array($choices) ? ($choices[0] ?? null) : null;
        $choice = is_array($firstChoice) ? ($firstChoice['message'] ?? null) : null;
        $content = is_array($choice) ? ($choice['content'] ?? $choice['refusal'] ?? null) : null;

        // Some compatible providers return text as content blocks instead of one string.
        if (is_array($content)) {
            $content = collect($content)
                ->filter(static fn (mixed $part): bool => is_array($part) && ($part['type'] ?? null) === 'text' && is_string($part['text'] ?? null))
                ->map(static fn (array $part): string => $part['text'])
                ->implode("\n");
        }

        if (! is_string($content) || trim($content) === '') {
            // Record only response shape metadata: never log the student's prompt or reply.
            $shape = [
                'response_keys' => is_array($body) ? array_keys($body) : [],
                'choices_type' => get_debug_type($choices),
                'choice_keys' => is_array($firstChoice) ? array_keys($firstChoice) : [],
                'message_keys' => is_array($choice) ? array_keys($choice) : [],
                'content_type' => get_debug_type($content),
                'finish_reason' => is_array($firstChoice) ? ($firstChoice['finish_reason'] ?? 'missing') : 'no-choice',
            ];
            throw new UnexpectedValueException('OpenRouter returned no text content. Response shape: ' . json_encode($shape, JSON_UNESCAPED_SLASHES));
        }

        return trim($content);
    }
}
