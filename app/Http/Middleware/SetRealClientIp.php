<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * We sit behind Cloudflare → Traefik → app. Cloudflare rewrites X-Forwarded-For
 * and exposes the real client IP in CF-Connecting-IP. TrustProxies alone only
 * peels Traefik off, so $request->ip() still returns a Cloudflare edge IP.
 *
 * This middleware prefers CF-Connecting-IP when the request arrived from a
 * Cloudflare range, falling back to standard TrustProxies handling otherwise.
 */
class SetRealClientIp
{
    /** Cloudflare published IP ranges (IPv4 only; IPv6 omitted for brevity). */
    private const CLOUDFLARE_RANGES = [
        '173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22',
        '141.101.64.0/18', '108.162.192.0/18', '190.93.240.0/20', '188.114.96.0/20',
        '197.234.240.0/22', '198.41.128.0/17', '162.158.0.0/15', '104.16.0.0/13',
        '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
    ];

    public function handle(Request $request, Closure $next)
    {
        $cfIp = $request->headers->get('CF-Connecting-IP');
        if ($cfIp && filter_var($cfIp, FILTER_VALIDATE_IP)) {
            $peer = $request->server->get('REMOTE_ADDR');
            // Trust CF-Connecting-IP only when the direct peer is actually
            // Cloudflare or a trusted private-range proxy (Traefik).
            if ($peer && (IpUtils::checkIp($peer, self::CLOUDFLARE_RANGES)
                || IpUtils::checkIp($peer, ['10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16', '127.0.0.0/8']))) {
                $request->server->set('REMOTE_ADDR', $cfIp);
                $request->headers->set('X-Forwarded-For', $cfIp);
            }
        }

        return $next($request);
    }
}
