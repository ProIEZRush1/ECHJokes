<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
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
        $url = $request->fullUrl();
        $params = $request->post();

        if (! $validator->validate($signature, $url, $params)) {
            abort(403, 'Invalid Twilio signature');
        }

        return $next($request);
    }
}
