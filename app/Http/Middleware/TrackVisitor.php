<?php

namespace App\Http\Middleware;

use App\Models\VisitorTouchpoint;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Cookie;

class TrackVisitor
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Only track GET requests on HTML responses
        if ($request->method() !== 'GET') return $response;

        $visitorId = $request->cookie('vacilada_vid');
        $isNewVisitor = false;
        if (!$visitorId) {
            $visitorId = (string) Str::uuid();
            $isNewVisitor = true;
        }

        // Attach cookie (30 days) on the way out
        if (method_exists($response, 'cookie')) {
            $response->cookie(new Cookie('vacilada_vid', $visitorId, time() + 60 * 60 * 24 * 30, '/', null, true, true, false, 'lax'));
        }

        $utm = [
            'utm_source' => $request->query('utm_source'),
            'utm_medium' => $request->query('utm_medium'),
            'utm_campaign' => $request->query('utm_campaign'),
            'utm_content' => $request->query('utm_content'),
            'utm_term' => $request->query('utm_term'),
        ];

        $hasUtm = collect($utm)->filter()->isNotEmpty();
        $userId = $request->user()?->id;

        // Log if: first visit, OR has UTM params, OR user just logged in (associate)
        if ($isNewVisitor || $hasUtm) {
            try {
                VisitorTouchpoint::create(array_merge($utm, [
                    'visitor_id' => $visitorId,
                    'user_id' => $userId,
                    'referrer' => substr((string) $request->headers->get('referer'), 0, 500),
                    'landing_page' => substr($request->fullUrl(), 0, 500),
                    'ip' => $request->ip(),
                    'user_agent' => substr((string) $request->userAgent(), 0, 500),
                    'is_first_touch' => $isNewVisitor,
                ]));
            } catch (\Throwable $e) {
                // Silent — don't break site if tracking fails
            }
        }

        return $response;
    }
}
