<?php

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class SecurityBaseline
{
    private const MAX_JSON_BYTES = 524288;

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('api/*') && in_array($request->method(), ['POST', 'PATCH', 'PUT'], true)) {
            $contentLength = (int) $request->header('Content-Length', '0');
            if ($contentLength > self::MAX_JSON_BYTES || strlen($request->getContent()) > self::MAX_JSON_BYTES) {
                return $this->secure(ApiResponse::error($request, 'Request body too large', 413));
            }

            if ($request->getContent() !== '' && ! $request->isJson()) {
                return $this->secure(ApiResponse::error($request, 'Unsupported request content type', 415));
            }
        }

        return $this->secure($next($request));
    }

    private function secure(Response $response): Response
    {
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        return $response;
    }
}
