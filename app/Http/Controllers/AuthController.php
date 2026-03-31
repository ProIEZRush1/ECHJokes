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
        ]);

        $email = $request->email;

        // Create user if doesn't exist
        $user = User::firstOrCreate(
            ['email' => $email],
            ['name' => explode('@', $email)[0], 'password' => '']
        );

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
