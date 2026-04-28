<?php

namespace App\Http\Controllers;

use App\Models\JokeCall;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class OgImageController extends Controller
{
    public function forCall(string $slug): \Symfony\Component\HttpFoundation\Response
    {
        $call = JokeCall::where('share_slug', $slug)->first();
        if (!$call || !$call->is_public) {
            return $this->fallback();
        }

        $creator = $call->user_id ? User::find($call->user_id) : null;
        $creatorName = $creator?->name ?: 'Alguien';
        $victimName = $call->victim_name ?: '';
        $scenarioSnippet = mb_substr((string) $call->custom_joke_prompt, 0, 110);
        if (mb_strlen((string) $call->custom_joke_prompt) > 110) $scenarioSnippet .= '…';

        $svg = $this->buildSvg([
            'creator' => $this->xmlEscape($creatorName),
            'victim' => $this->xmlEscape($victimName),
            'scenario' => $this->xmlEscape($scenarioSnippet),
            'views' => (int) $call->share_views,
        ]);

        return Response::make($svg, 200, [
            'Content-Type' => 'image/svg+xml; charset=utf-8',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    private function fallback(): \Symfony\Component\HttpFoundation\Response
    {
        $path = public_path('brand/og-image.png');
        return Response::file($path, ['Content-Type' => 'image/png']);
    }

    private function xmlEscape(string $s): string
    {
        return str_replace(['&', '<', '>', '"', "'"], ['&amp;', '&lt;', '&gt;', '&quot;', '&#39;'], $s);
    }

    private function buildSvg(array $d): string
    {
        $creator = $d['creator'];
        $victim = $d['victim'];
        $scenario = $d['scenario'];
        $views = $d['views'];
        $hero = $victim
            ? "{$creator} <tspan fill=\"#ffffff\" font-weight=\"400\" font-size=\"56\">le hizo una vacilada a</tspan> {$victim}"
            : "Vacilada de {$creator}";

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 630" width="1200" height="630">
  <defs>
    <linearGradient id="bg" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0%" stop-color="#0a0f0a"/>
      <stop offset="100%" stop-color="#111a11"/>
    </linearGradient>
    <filter id="glow" x="-50%" y="-50%" width="200%" height="200%">
      <feGaussianBlur stdDeviation="4" result="blur"/>
      <feMerge><feMergeNode in="blur"/><feMergeNode in="SourceGraphic"/></feMerge>
    </filter>
  </defs>
  <rect width="1200" height="630" fill="url(#bg)"/>

  <g opacity="0.06" font-family="monospace" font-size="16" fill="#39ff14">
    <text x="40" y="40">V A C 1 L 4 D 4</text>
    <text x="40" y="600">B R O M 4 5 0 1</text>
    <text x="1060" y="60">0 1 1 0 1</text>
    <text x="1080" y="590">V A C</text>
  </g>

  <g transform="translate(80 170)">
    <g transform="scale(1.3)">
      <g transform="rotate(-30)">
        <path d="M -56 -18 Q -56 -34, -40 -34 L -28 -34 Q -18 -34, -14 -22 L -8 -4 Q -6 2, -10 6 L -18 12 Q -14 32, 0 48 L 6 54 Q 12 60, 18 56 L 30 48 Q 38 42, 44 48 L 56 60 Q 66 70, 56 82 L 48 90 Q 38 100, 24 96 Q -12 88, -42 58 Q -72 28, -76 -8 Q -78 -18, -70 -22 Z" fill="#39ff14" filter="url(#glow)"/>
      </g>
      <path d="M 20 -60 Q 42 -56, 50 -30" fill="none" stroke="#39ff14" stroke-width="8" stroke-linecap="round"/>
      <circle cx="38" cy="-72" r="5" fill="#39ff14"/>
      <circle cx="58" cy="-60" r="5" fill="#39ff14"/>
    </g>
  </g>

  <g transform="translate(270 0)">
    <text x="0" y="120" font-family="'JetBrains Mono', monospace" font-size="36" fill="#39ff14" opacity="0.85">Vacilada</text>
    <text x="0" y="210" font-family="'Inter', sans-serif" font-size="56" font-weight="800" fill="#39ff14" filter="url(#glow)">
      <tspan>{$hero}</tspan>
    </text>
    <foreignObject x="0" y="280" width="850" height="140">
      <div xmlns="http://www.w3.org/1999/xhtml" style="font-family:Inter,sans-serif;font-size:26px;color:#c7d2c7;line-height:1.4;">
        {$scenario}
      </div>
    </foreignObject>
    <text x="0" y="520" font-family="'Inter', sans-serif" font-size="22" fill="#9aa59a">
      <tspan>👁 {$views} escuchas</tspan>
      <tspan dx="40" fill="#39ff14">→ vacilada.com</tspan>
    </text>
  </g>
</svg>
SVG;
    }
}
