<?php

namespace App\Services;

use App\Models\JokeCall;
use Illuminate\Support\Facades\Log;
use Twilio\Rest\Client;

class TwilioCallService
{
    private Client $client;
    private string $fromNumber;

    public function __construct()
    {
        $this->client = new Client(
            config('services.twilio.sid'),
            config('services.twilio.auth_token')
        );
        $this->fromNumber = config('services.twilio.phone_number');
    }

    /**
     * Initiate a phone call to deliver the joke.
     *
     * @return string The Twilio Call SID
     */
    public function initiateCall(JokeCall $jokeCall): string
    {
        try {
            $options = [
                'url' => route('twilio.voice', ['jokeCall' => $jokeCall->id]),
                'statusCallback' => route('twilio.status'),
                'statusCallbackEvent' => ['initiated', 'ringing', 'answered', 'completed'],
                'statusCallbackMethod' => 'POST',
                'timeout' => 30,
            ];

            // Enable recording
            if (env('TWILIO_RECORDING_ENABLED', false)) {
                $options['record'] = true;
                $options['recordingStatusCallback'] = route('twilio.recording');
                $options['recordingStatusCallbackEvent'] = ['completed'];
            }

            $call = $this->client->calls->create(
                $jokeCall->callablePhone(),
                $this->fromNumber,
                $options
            );

            return $call->sid;
        } catch (\Throwable $e) {
            Log::error('Twilio call failed', [
                'joke_call_id' => $jokeCall->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Look up phone number type via Twilio Lookup API.
     *
     * @return array{type: string|null, valid: bool}
     */
    public function lookupPhoneNumber(string $phoneNumber): array
    {
        try {
            $lookup = $this->client->lookups->v2->phoneNumbers($phoneNumber)
                ->fetch(['fields' => 'line_type_intelligence']);

            $lineType = $lookup->lineTypeIntelligence['type'] ?? null;

            return [
                'type' => $lineType,
                'valid' => true,
            ];
        } catch (\Throwable $e) {
            Log::warning('Phone lookup failed', ['phone' => substr($phoneNumber, -4), 'error' => $e->getMessage()]);
            return ['type' => null, 'valid' => true]; // Allow on lookup failure
        }
    }

    /**
     * Send a WhatsApp message with the joke.
     */
    public function sendWhatsApp(JokeCall $jokeCall): string
    {
        try {
            $jokeText = $jokeCall->joke_text ?? '';
            $body = "ECHJokes te manda un chiste:\n\n{$jokeText}\n\nQuieres mandar uno tu tambien? → echjokes.mx";

            $message = $this->client->messages->create(
                'whatsapp:' . $jokeCall->callablePhone(),
                [
                    'from' => 'whatsapp:' . $this->fromNumber,
                    'body' => $body,
                ]
            );

            return $message->sid;
        } catch (\Throwable $e) {
            Log::error('WhatsApp send failed', [
                'joke_call_id' => $jokeCall->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
