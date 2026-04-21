<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

class AuthController extends Controller
{
    /**
     * Send a magic link email.
     */
    public function sendMagicLink(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'ref' => 'nullable|string|max:16',
        ]);

        $email = $request->email;
        $existing = User::where('email', $email)->first();
        $isNew = ! $existing;

        $user = $existing ?? User::create(['email' => $email, 'name' => explode('@', $email)[0], 'password' => '']);

        if ($isNew && $request->ref) {
            $referrer = User::where('referral_code', strtoupper($request->ref))->first();
            if ($referrer && $referrer->id !== $user->id) {
                $user->referred_by_user_id = $referrer->id;
                $user->save();
            }
        }

        // Generate signed URL valid for 15 minutes
        $url = URL::temporarySignedRoute(
            'auth.verify',
            now()->addMinutes(15),
            ['user' => $user->id]
        );

        // Send email with magic link
        Mail::raw(
            "Hola! Haz clic para iniciar sesion en ECHJokes:\n\n{$url}\n\nEste link expira en 15 minutos.",
            function ($message) use ($email) {
                $message->to($email)->subject('Tu link magico de ECHJokes');
            }
        );

        return response()->json(['message' => 'Link enviado a tu correo.']);
    }

    /**
     * Verify magic link and log in.
     */
    public function verifyMagicLink(Request $request, User $user)
    {
        if (! $request->hasValidSignature()) {
            return redirect('/login?error=expired');
        }

        Auth::login($user, remember: true);

        return redirect('/dashboard');
    }

    /**
     * Get the authenticated user.
     */
    public function user(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(null);
        }

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'is_admin' => $user->isAdmin(),
            'subscription_plan' => $user->subscription_plan,
            'credits_remaining' => $user->creditsRemaining(),
            'referral_code' => $user->referral_code,
        ]);
    }

    public function referralInfo(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) return response()->json(null, 401);

        $referred = User::where('referred_by_user_id', $user->id)->get();
        $successful = $referred->whereNotNull('referral_credited_at')->count();
        $base = rtrim(config('app.url'), '/');

        return response()->json([
            'code' => $user->referral_code,
            'link' => $base . '/?ref=' . $user->referral_code,
            'share_text' => "Bromas telefonicas con IA! Usa mi link y los dos ganamos 2 bromas gratis: {$base}/?ref={$user->referral_code}",
            'referred_total' => $referred->count(),
            'referred_successful' => $successful,
            'credits_earned' => $successful * 2,
        ]);
    }

    /**
     * Log out.
     */
    public function logout(Request $request): JsonResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Sesion cerrada.']);
    }
}
