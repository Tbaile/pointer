<?php

namespace App\Http\Middleware;

use App\Events\McpToolCalled;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Laravel\Passport\AccessToken;
use Laravel\Passport\Passport;
use Symfony\Component\HttpFoundation\Response;

class LogMcpToolCalls
{
    /**
     * Dispatch an McpToolCalled event for every MCP tool call.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = microtime(true);

        $response = $next($request);

        if ($request->json('method') !== 'tools/call') {
            return $response;
        }

        $user = $request->user();
        $token = $user instanceof User ? $user->token() : null;
        $body = json_decode((string) $response->getContent(), true);

        McpToolCalled::dispatch(
            $user,
            $token instanceof AccessToken ? Passport::client()->find($token->oauth_client_id)?->name : null,
            $request->header('Mcp-Session-Id'),
            $request->json('params.name'),
            $request->json('params.arguments'),
            ! ($body['result']['isError'] ?? true),
            (int) round((microtime(true) - $startedAt) * 1000),
        );

        return $response;
    }
}
