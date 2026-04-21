<?php

namespace App\Http\Controllers;

use App\Models\JokeCall;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class InboundCallController extends Controller
{
    public function handle(Request $request): Response
    {
        $from = $request->input('From', '');
        $callSid = $request->input('CallSid', '');

        Log::info('Inbound call', ['from' => $from, 'call_sid' => $callSid]);

        // Check if this caller was pranked before
        $lastPrank = JokeCall::where('phone_number', $from)
            ->whereIn('status', ['completed', 'in_progress'])
            ->whereNotNull('custom_joke_prompt')
            ->latest()
            ->first();

        if ($lastPrank && $lastPrank->custom_joke_prompt) {
            // Continue the prank! Connect to WebSocket with the same scenario
            $scenario = $lastPrank->custom_joke_prompt;
            $character = 'La misma persona que llamo antes. La persona te esta devolviendo la llamada. Actua sorprendido de que te llamen y continua la broma donde se quedo.';
            $voice = 'ash';

            $payload = base64_encode(json_encode([
                's' => $scenario . "\n\nCONTEXTO: Esta persona te esta DEVOLVIENDO la llamada. Tu le llamaste antes con esta broma. Actua sorprendido y continua donde te quedaste.",
                'c' => $character,
                'v' => $voice,
            ]));

            $streamUrl = 'wss://ws.vacilada.com/stream/' . $payload;

            return $this->twiml(
                '<Connect><Stream url="' . e($streamUrl) . '" /></Connect>'
            );
        }

        // No previous prank — tell a quick joke and hang up
        $jokes = [
            'Oye, sabias que si le pones lentes a un pez se convierte en pez con vista? Bueno, eso no es cierto, pero que chistoso seria no? Gracias por llamar a Vacilada, la mejor app de bromas telefonicas con inteligencia artificial. Visitanos en vacilada punto com. Adios!',
            'Hola! Sabias que los gatos planean dominar el mundo? Lo se porque mi gato me mira raro cuando como atun. En fin, gracias por llamar a Vacilada! Si quieres hacer bromas telefonicas con IA, visitanos en vacilada punto com. Hasta luego!',
            'Bueno? Ah hola! Fijate que ayer le pregunte a la IA que es el amor y me dijo error cuatro cero cuatro. Ni la tecnologia lo entiende. Gracias por llamar a Vacilada, bromas telefonicas con inteligencia artificial. Vacilada punto com. Bye!',
        ];

        $joke = $jokes[array_rand($jokes)];

        return $this->twiml(
            '<Say language="es-MX" voice="Polly.Mia">' . e($joke) . '</Say><Hangup/>'
        );
    }

    private function twiml(string $body): Response
    {
        return response(
            '<?xml version="1.0" encoding="UTF-8"?><Response>' . $body . '</Response>',
            200,
            ['Content-Type' => 'text/xml']
        );
    }
}
