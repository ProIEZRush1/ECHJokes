<?php

namespace App\Services;

use App\Models\JokeCall;
use Illuminate\Support\Facades\Log;
use Twilio\Rest\Client;

class WhatsAppService
{
    private Client $client;
    private string $fromNumber;

    public function __construct()
    {
        $this->client = new Client(
            config('services.twilio.sid'),
            config('services.twilio.auth_token')
        );
        $this->fromNumber = env('TWILIO_WHATSAPP_NUMBER', config('services.twilio.phone_number'));
    }

    /**
     * Send a prank message via WhatsApp.
     */
    public function sendPrank(JokeCall $jokeCall, array $prankScript): string
    {
        $character = $prankScript['character'] ?? 'representante';
        $opening = $prankScript['opening'] ?? '';
        $scenario = $jokeCall->custom_joke_prompt ?? '';

        $body = "🎭 *Vacilada* — Broma telefonica por WhatsApp\n\n"
            . "📞 *{$character}*:\n"
            . "\"{$opening}\"\n\n"
            . "---\n"
            . "Esta broma fue inspirada en: _{$scenario}_\n\n"
            . "¿Quieres mandar una broma tu tambien? → vacilada.mx";

        if ($jokeCall->is_gift && $jokeCall->sender_name) {
            $body .= "\n\nEnviado por: {$jokeCall->sender_name}";
        }

        try {
            $message = $this->client->messages->create(
                'whatsapp:' . $jokeCall->callablePhone(),
                [
                    'from' => 'whatsapp:' . $this->fromNumber,
                    'body' => $body,
                ]
            );

            return $message->sid;
        } catch (\Throwable $e) {
            Log::error('WhatsApp delivery failed', [
                'joke_call_id' => $jokeCall->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
