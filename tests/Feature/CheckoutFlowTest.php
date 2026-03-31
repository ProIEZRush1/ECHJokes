<?php

namespace Tests\Feature;

use App\Enums\JokeCallStatus;
use App\Models\JokeCall;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_validates_phone_number(): void
    {
        $response = $this->postJson('/checkout', [
            'phone_number' => '123',
            'scenario' => 'Que llamen del banco por un cargo sospechoso',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('phone_number');
    }

    public function test_checkout_validates_scenario_required(): void
    {
        $response = $this->postJson('/checkout', [
            'phone_number' => '5512345678',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('scenario');
    }

    public function test_checkout_validates_scenario_min_length(): void
    {
        $response = $this->postJson('/checkout', [
            'phone_number' => '5512345678',
            'scenario' => 'corto',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('scenario');
    }

    public function test_checkout_rejects_phone_starting_with_zero(): void
    {
        $response = $this->postJson('/checkout', [
            'phone_number' => '0512345678',
            'scenario' => 'Que llamen diciendo que su carro tiene una multa pendiente',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('phone_number');
    }

    public function test_checkout_rate_limits_same_phone(): void
    {
        for ($i = 0; $i < 3; $i++) {
            JokeCall::create([
                'session_id' => "test-session-{$i}",
                'phone_number' => '+525512345678',
                'joke_category' => 'prank',
                'status' => JokeCallStatus::Completed,
                'ip_address' => '127.0.0.1',
            ]);
        }

        $response = $this->postJson('/checkout', [
            'phone_number' => '5512345678',
            'scenario' => 'Que llamen del banco por un cargo sospechoso',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('phone_number');
    }

    public function test_checkout_validates_gift_fields_when_gift(): void
    {
        $response = $this->postJson('/checkout', [
            'phone_number' => '5512345678',
            'scenario' => 'Que llamen diciendo que gano un concurso de canto',
            'is_gift' => true,
        ]);

        $response->assertStatus(422);
    }

    public function test_checkout_validates_delivery_type(): void
    {
        $response = $this->postJson('/checkout', [
            'phone_number' => '5512345678',
            'scenario' => 'Que llamen de la CFE diciendo que debe 3 meses de luz',
            'delivery_type' => 'smoke_signal',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('delivery_type');
    }
}
