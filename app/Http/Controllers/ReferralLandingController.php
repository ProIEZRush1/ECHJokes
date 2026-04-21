<?php

namespace App\Http\Controllers;

use App\Models\JokeCall;
use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;

class ReferralLandingController extends Controller
{
    public function show(string $code, Request $request)
    {
        $referrer = User::where('referral_code', strtoupper($code))->first();

        // Store the ref code in cookie + session for 30 days regardless (so attribution works even if user is null)
        session(['echjokes_ref' => strtoupper($code)]);

        if (!$referrer) {
            return redirect('/')->withCookie(new Cookie('vacilada_ref', strtoupper($code), time() + 60 * 60 * 24 * 30, '/', null, true, false, false, 'lax'));
        }

        $bestCalls = JokeCall::where('user_id', $referrer->id)
            ->where('is_public', true)
            ->whereNotNull('recording_url')
            ->orderByDesc('share_views')
            ->orderByDesc('created_at')
            ->limit(3)
            ->get(['share_slug', 'custom_joke_prompt', 'victim_name', 'created_at']);

        return response()
            ->view('referral-landing', [
                'referrer' => $referrer,
                'bestCalls' => $bestCalls,
                'code' => strtoupper($code),
            ])
            ->withCookie(new Cookie('vacilada_ref', strtoupper($code), time() + 60 * 60 * 24 * 30, '/', null, true, false, false, 'lax'));
    }
}
