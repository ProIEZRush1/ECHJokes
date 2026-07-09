<?php

namespace Tests\Feature;

use App\Enums\JokeCallStatus;
use App\Models\JokeCall;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AssistantCallTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(bool $admin): User
    {
        return User::create([
            'name' => $admin ? 'Admin' : 'Regular',
            'email' => ($admin ? 'admin' : 'user') . '@test.com',
            'password' => bcrypt('secret'),
            'is_admin' => $admin,
        ]);
    }

    /** URL-safe base64 decode matching websocket-server/server.js. */
    private function decodeStreamPayload(string $twiml): array
    {
        preg_match('#/stream/([^"]+)"#', $twiml, $m);
        $seg = $m[1] ?? '';
        $std = strtr($seg, '-_', '+/');
        $std .= str_repeat('=', (4 - strlen($std) % 4) % 4);
        return json_decode(base64_decode($std), true) ?? [];
    }

    public function test_conversation_start_assistant_returns_stream_from_db(): void
    {
        JokeCall::create([
            'session_id' => 'a1',
            'phone_number' => '+528001234567',
            'joke_category' => 'assistant',
            'call_type' => 'assistant',
            'twilio_call_sid' => 'CA_assist_1',
            'assistant_objective' => '¿Puedo cambiar mi vuelo? Separar 2 pasajeros.',
            'assistant_context' => 'Reserva ABC/123',
            'assistant_identity' => 'Juan Pérez',
            'assistant_company' => 'Volaris',
            'voice' => 'ash',
            'status' => JokeCallStatus::Calling,
            'ip_address' => '127.0.0.1',
        ]);

        $res = $this->post('/conversation/start', [
            'CallSid' => 'CA_assist_1',
            'mode' => 'assistant',
        ]);

        $res->assertStatus(200);
        $twiml = $res->getContent();
        $this->assertStringContainsString('<Connect><Stream', $twiml);

        // Payload must be URL-safe base64 (no raw '+' or '/' in the segment) and
        // must reflect the values stored in the DB, not the (absent) query args.
        preg_match('#/stream/([^"]+)"#', $twiml, $m);
        $this->assertStringNotContainsString('/', $m[1] ?? 'x/');
        $this->assertStringNotContainsString('+', $m[1] ?? 'x+');

        $payload = $this->decodeStreamPayload($twiml);
        $this->assertSame('assistant', $payload['m']);
        $this->assertSame('¿Puedo cambiar mi vuelo? Separar 2 pasajeros.', $payload['o']);
        $this->assertSame('Reserva ABC/123', $payload['x']);
        $this->assertSame('Juan Pérez', $payload['i']);
        $this->assertSame('Volaris', $payload['co']);
    }

    public function test_prank_stream_payload_is_url_safe(): void
    {
        // A scenario with '?' used to produce a '/' in base64 that truncated the
        // stream URL. Assert the encoded segment is URL-safe now.
        $res = $this->post('/conversation/start', [
            'CallSid' => 'CA_prank_1',
            'scenario' => '¿Bueno? ¿Está el encargado?',
            'character' => 'Serio/formal',
            'voice' => 'ash',
            'victim_name' => 'María',
        ]);

        $res->assertStatus(200);
        $payload = $this->decodeStreamPayload($res->getContent());
        $this->assertSame('¿Bueno? ¿Está el encargado?', $payload['s']);
        $this->assertSame('Serio/formal', $payload['c']);
        $this->assertSame('María', $payload['n']);
    }

    public function test_call_question_sets_and_clears_pending(): void
    {
        $call = JokeCall::create([
            'session_id' => 'q1',
            'phone_number' => '+528001234567',
            'joke_category' => 'assistant',
            'call_type' => 'assistant',
            'twilio_call_sid' => 'CA_q_1',
            'status' => JokeCallStatus::InProgress,
            'ip_address' => '127.0.0.1',
        ]);

        $this->post('/api/call-question', ['call_sid' => 'CA_q_1', 'question' => '¿Ventana o pasillo?'])
            ->assertStatus(200);
        $this->assertSame('¿Ventana o pasillo?', $call->fresh()->pending_question);

        $this->post('/api/call-question', ['call_sid' => 'CA_q_1', 'cleared' => true])
            ->assertStatus(200);
        $this->assertNull($call->fresh()->pending_question);
    }

    public function test_status_completed_marks_assistant_completed_with_transcript(): void
    {
        Http::fake(['api.anthropic.com/*' => Http::response(['content' => [['text' => 'Objetivo cumplido.']]], 200)]);

        $call = JokeCall::create([
            'session_id' => 'c1',
            'phone_number' => '+528001234567',
            'joke_category' => 'assistant',
            'call_type' => 'assistant',
            'delivery_type' => 'call',
            'twilio_call_sid' => 'CA_c_1',
            'status' => JokeCallStatus::InProgress,
            'live_transcript' => json_encode([['role' => 'human', 'text' => 'Volaris, buenas', 'at' => '10:00:00']]),
            'ip_address' => '127.0.0.1',
        ]);

        $this->post('/webhooks/twilio/status', [
            'CallSid' => 'CA_c_1', 'CallStatus' => 'completed', 'CallDuration' => '120',
        ])->assertStatus(200);

        $this->assertSame(JokeCallStatus::Completed, $call->fresh()->status);
        $this->assertSame(120, $call->fresh()->call_duration_seconds);
    }

    public function test_completed_assistant_call_gets_a_summary(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [['text' => 'La IA marcó la opción 2, confirmó la reserva ABC123 y la empresa la canceló. Pendiente: revisar el reembolso.']],
            ], 200),
        ]);

        $call = JokeCall::create([
            'session_id' => 'sum1',
            'phone_number' => '+528001234567',
            'joke_category' => 'assistant',
            'call_type' => 'assistant',
            'delivery_type' => 'call',
            'twilio_call_sid' => 'CA_sum_1',
            'assistant_objective' => 'Cancelar y reembolsar la reserva',
            'status' => JokeCallStatus::InProgress,
            'live_transcript' => json_encode([
                ['role' => 'ai', 'text' => 'Hola, llamo para cancelar una reserva', 'at' => '10:00:00'],
                ['role' => 'human', 'text' => 'Con gusto, deme el número', 'at' => '10:00:05'],
                ['role' => 'dtmf', 'text' => '⌨️ ABC123', 'at' => '10:00:08'],
            ]),
            'ip_address' => '127.0.0.1',
        ]);

        $this->post('/webhooks/twilio/status', [
            'CallSid' => 'CA_sum_1', 'CallStatus' => 'completed', 'CallDuration' => '90',
        ])->assertStatus(200);

        $this->assertStringContainsString('reembolso', $call->fresh()->assistant_summary);
    }

    public function test_status_completed_marks_assistant_failed_when_never_connected(): void
    {
        $call = JokeCall::create([
            'session_id' => 'c2',
            'phone_number' => '+528001234567',
            'joke_category' => 'assistant',
            'call_type' => 'assistant',
            'delivery_type' => 'call',
            'twilio_call_sid' => 'CA_c_2',
            'status' => JokeCallStatus::InProgress,
            'ip_address' => '127.0.0.1',
        ]);

        $this->post('/webhooks/twilio/status', [
            'CallSid' => 'CA_c_2', 'CallStatus' => 'completed', 'CallDuration' => '30',
        ])->assertStatus(200);

        $this->assertSame(JokeCallStatus::Failed, $call->fresh()->status);
    }

    public function test_assistant_failed_call_gets_clear_reason_without_credit_language(): void
    {
        $call = JokeCall::create([
            'session_id' => 'f1',
            'phone_number' => '+528005071200', // Mexican 800 toll-free
            'joke_category' => 'assistant',
            'call_type' => 'assistant',
            'delivery_type' => 'call',
            'twilio_call_sid' => 'CA_f_1',
            'status' => JokeCallStatus::Calling,
            'ip_address' => '127.0.0.1',
        ]);

        $this->post('/webhooks/twilio/status', [
            'CallSid' => 'CA_f_1', 'CallStatus' => 'failed',
        ])->assertStatus(200);

        $reason = $call->fresh()->failure_reason;
        $this->assertSame(JokeCallStatus::Failed, $call->fresh()->status);
        $this->assertStringContainsString('800', $reason);          // toll-free hint
        $this->assertStringNotContainsString('Crédito', $reason);   // no billing language
    }

    public function test_launch_assistant_call_requires_admin(): void
    {
        $this->actingAs($this->makeUser(false))
            ->postJson('/admin-api/launch-assistant-call', [
                'phone_number' => '8001234567',
                'objective' => 'Cambiar mi vuelo',
            ])
            ->assertStatus(403);
    }

    public function test_ws_control_token_requires_admin(): void
    {
        $this->actingAs($this->makeUser(false))
            ->getJson('/admin-api/ws-control-token')
            ->assertStatus(403);

        $this->actingAs($this->makeUser(true))
            ->getJson('/admin-api/ws-control-token')
            ->assertStatus(200)
            ->assertJsonStructure(['token']);
    }
}
