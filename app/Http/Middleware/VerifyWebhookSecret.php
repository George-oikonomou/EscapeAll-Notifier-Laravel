<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyWebhookSecret
{
    /**
     * Verify that the incoming request carries the correct webhook secret.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $expected = config('services.webhook.secret');

        if (empty($expected)) {
            abort(500, 'WEBHOOK_SECRET is not configured on the server.');
        }

        $provided = $request->header('X-Webhook-Secret', '');

        if (! hash_equals($expected, $provided)) {
            abort(403, 'Invalid webhook secret.');
        }

        return $next($request);
    }
}
