<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Passport\AccessToken;
use Laravel\Passport\Passport;
use Symfony\Component\HttpFoundation\Response;

class LogMcpToolCalls
{
    /**
     * Write one line to the mcp log channel for every MCP tool call.
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

        Log::channel('mcp')->info('tools/call', [
            'user_id' => $user?->getAuthIdentifier(),
            'user_email' => $user instanceof User ? $user->email : null,
            'client' => $token instanceof AccessToken ? Passport::client()->find($token->oauth_client_id)?->name : null,
            'session_id' => $request->header('Mcp-Session-Id'),
            'tool' => $request->json('params.name'),
            'arguments' => $request->json('params.arguments'),
            'success' => ! ($body['result']['isError'] ?? true),
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ]);

        return $response;
    }
}
