<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Twilio\Security\RequestValidator;

class VerifyTwilioSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->environment('local', 'testing')) {
            return $next($request);
        }

        $validator = new RequestValidator(config('services.twilio.auth_token'));
        $signature = $request->header('X-Twilio-Signature', '');

        // Twilio signs with the public HTTPS URL, but behind a reverse proxy
        // the request may arrive as HTTP. Force HTTPS for validation.
        $url = str_replace('http://', 'https://', $request->fullUrl());
        $params = $request->post();

        if (! $validator->validate($signature, $url, $params)) {
            Log::warning('Twilio signature validation failed', [
                'url' => $url,
                'has_signature' => !empty($signature),
            ]);
            abort(403, 'Invalid Twilio signature');
        }

        return $next($request);
    }
}
