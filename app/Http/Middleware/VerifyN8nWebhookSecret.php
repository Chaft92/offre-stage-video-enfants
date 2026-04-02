<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyN8nWebhookSecret
{
    public function handle(Request $request, Closure $next): Response
    {
        $expectedSecret = config('services.n8n.secret');

        if (!empty($expectedSecret)) {
            $provided = (string) $request->header('X-N8N-Secret', '');

            if (!hash_equals($expectedSecret, $provided)) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }
        }

        return $next($request);
    }
}
