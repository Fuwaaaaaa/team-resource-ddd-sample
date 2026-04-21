<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * リクエスト毎に一意な request_id を割り当て、
 *   - Laravel 11+ の Context ファサードに載せて全ログ行に横串で付与
 *   - X-Request-Id ヘッダでレスポンスに echo
 *
 * 既に X-Request-Id ヘッダが付いているリクエストはその値をそのまま尊重する
 * （同一リクエストをフロント/CDN/オブザーバビリティ基盤で追跡するため）。
 */
final class AssignRequestId
{
    public function handle(Request $request, Closure $next): Response
    {
        $id = $request->header('X-Request-Id');
        if (! is_string($id) || $id === '') {
            $id = (string) Str::uuid7();
        }

        Context::add('request_id', $id);

        /** @var Response $response */
        $response = $next($request);
        $response->headers->set('X-Request-Id', $id);

        return $response;
    }
}
