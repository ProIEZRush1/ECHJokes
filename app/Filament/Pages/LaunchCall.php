<?php

namespace App\Filament\Pages;

use App\Enums\JokeCallStatus;
use App\Models\JokeCall;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Str;

class LaunchCall extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-phone-arrow-up-right';
    protected static ?string $navigationLabel = 'Launch Call';
    protected static ?int $navigationSort = 0;

    protected static string $view = 'filament.pages.launch-call';

    public string $phone_number = '';
    public string $scenario = '';
    public string $character = 'administrador del condominio';
    public string $result = '';

    public function launchCall(): void
    {
        $phone = $this->phone_number;
        if (!str_starts_with($phone, '+')) {
            $phone = '+52' . $phone;
        }

        $jokeCall = JokeCall::create([
            'session_id' => Str::ulid()->toBase32(),
            'phone_number' => $phone,
            'joke_category' => 'prank',
            'joke_source' => 'custom',
            'custom_joke_prompt' => $this->scenario,
            'delivery_type' => 'call',
            'status' => JokeCallStatus::Calling,
            'ip_address' => request()->ip(),
        ]);

        try {
            $twilio = new \Twilio\Rest\Client(
                config('services.twilio.sid'),
                config('services.twilio.auth_token')
            );

            $call = $twilio->calls->create($phone, config('services.twilio.phone_number'), [
                'url' => url('/conversation/start') . '?scenario=' . urlencode($this->scenario) . '&character=' . urlencode($this->character),
                'method' => 'POST',
                'timeout' => 45,
                'record' => true,
            ]);

            $jokeCall->update(['twilio_call_sid' => $call->sid]);
            $this->result = "Call initiated! SID: {$call->sid}";

            Notification::make()->title('Call initiated!')->body("Calling {$phone}")->success()->send();
        } catch (\Throwable $e) {
            $jokeCall->update(['status' => JokeCallStatus::Failed, 'failure_reason' => $e->getMessage()]);
            $this->result = "Error: {$e->getMessage()}";

            Notification::make()->title('Call failed')->body($e->getMessage())->danger()->send();
        }
    }
}
