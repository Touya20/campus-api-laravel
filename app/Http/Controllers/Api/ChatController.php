<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChatRequest;
use App\Services\Chat\OpenRouterChatService;
use App\Support\ApiResponse;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use UnexpectedValueException;

final class ChatController extends Controller
{
    public function __construct(private readonly OpenRouterChatService $chatService)
    {
    }

    public function reply(ChatRequest $request): JsonResponse
    {
        if (! filled(config('services.openrouter.api_key'))) {
            return ApiResponse::error($request, 'AI chat is not configured on the server.', 503);
        }

        try {
            $reply = $this->chatService->reply($request->validated('messages'));
        } catch (RequestException $exception) {
            Log::warning('Campus AI provider rejected the chat request.', [
                'exception' => $exception::class,
                'provider_status' => $exception->response->status(),
            ]);

            return ApiResponse::error($request, 'Campus Helper could not get a reply right now. Please try again.', 502);
        } catch (ConnectionException $exception) {
            Log::warning('Campus AI provider could not be reached.', ['exception' => $exception::class]);

            return ApiResponse::error($request, 'Campus Helper could not reach the AI service. Please try again.', 502);
        } catch (UnexpectedValueException $exception) {
            Log::warning('Campus AI provider returned an unusable reply.', ['reason' => $exception->getMessage()]);

            return ApiResponse::error($request, 'Campus Helper could not get a reply right now. Please try again.', 502);
        }

        return ApiResponse::success($request, ['reply' => $reply]);
    }
}
