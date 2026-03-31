<?php

namespace Tests\Feature;

use App\Enums\JokeCallStatus;
use App\Models\JokeCall;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TwilioWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_voice_webhook_returns_twiml(): void
    {
        $jokeCall = JokeCall::create([
            'session_id' => 'test-voice',
            'phone_number' => '+525512345678',
            'joke_category' => 'general',
            'status' => JokeCallStatus::Calling,
            'joke_text' => 'Test joke',
            'audio_file_path' => 'audio/test.mp3',
            'ip_address' => '127.0.0.1',
        ]);

        // VerifyTwilioSignature middleware is skipped in testing env
        $response = $this->post("/webhooks/twilio/voice/{$jokeCall->id}");

        $response->assertStatus(200);
        $this->assertStringContainsString('text/xml', $response->headers->get('Content-Type'));

        $content = $response->getContent();
        $this->assertStringContainsString('<Response>', $content);
    }

    public function test_status_callback_completes_call(): void
    {
        $jokeCall = JokeCall::create([
            'session_id' => 'test-status',
            'phone_number' => '+525512345678',
            'joke_category' => 'general',
            'status' => JokeCallStatus::InProgress,
            'twilio_call_sid' => 'CA_test_123',
            'ip_address' => '127.0.0.1',
        ]);

        $response = $this->post('/webhooks/twilio/status', [
            'CallSid' => 'CA_test_123',
            'CallStatus' => 'completed',
            'CallDuration' => '45',
        ]);

        $response->assertStatus(200);

        $jokeCall->refresh();
        $this->assertEquals(JokeCallStatus::Completed, $jokeCall->status);
        $this->assertEquals(45, $jokeCall->call_duration_seconds);
    }

    public function test_status_callback_handles_no_answer(): void
    {
        $jokeCall = JokeCall::create([
            'session_id' => 'test-noanswer',
            'phone_number' => '+525512345678',
            'joke_category' => 'general',
            'status' => JokeCallStatus::Calling,
            'twilio_call_sid' => 'CA_test_456',
            'ip_address' => '127.0.0.1',
        ]);

        $response = $this->post('/webhooks/twilio/status', [
            'CallSid' => 'CA_test_456',
            'CallStatus' => 'no-answer',
        ]);

        $response->assertStatus(200);

        $jokeCall->refresh();
        $this->assertEquals(JokeCallStatus::Failed, $jokeCall->status);
    }

    public function test_status_callback_ignores_unknown_call_sid(): void
    {
        $response = $this->post('/webhooks/twilio/status', [
            'CallSid' => 'CA_unknown',
            'CallStatus' => 'completed',
        ]);

        $response->assertStatus(200);
    }

    public function test_recording_webhook_stores_recording_data(): void
    {
        \Illuminate\Support\Facades\Queue::fake();

        $jokeCall = JokeCall::create([
            'session_id' => 'test-recording',
            'phone_number' => '+525512345678',
            'joke_category' => 'general',
            'status' => JokeCallStatus::Completed,
            'twilio_call_sid' => 'CA_test_789',
            'ip_address' => '127.0.0.1',
        ]);

        $response = $this->post('/webhooks/twilio/recording', [
            'CallSid' => 'CA_test_789',
            'RecordingSid' => 'RE_test_abc',
            'RecordingUrl' => 'https://api.twilio.com/recording/RE_test_abc',
            'RecordingDuration' => '30',
        ]);

        $response->assertStatus(200);

        $jokeCall->refresh();
        $this->assertEquals('RE_test_abc', $jokeCall->recording_sid);
        $this->assertEquals(30, $jokeCall->recording_duration_sec);
    }
}
