<?php

namespace Tests\Unit;

use App\Models\JokeCall;
use App\Services\CostTrackingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CostTrackingServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_estimate_cost_for_basic_call(): void
    {
        $jokeCall = JokeCall::create([
            'session_id' => 'test-cost',
            'phone_number' => '+525512345678',
            'joke_category' => 'general',
            'status' => 'completed',
            'joke_text' => str_repeat('a', 200), // 200 chars
            'call_duration_seconds' => 60,
            'ip_address' => '127.0.0.1',
        ]);

        $service = new CostTrackingService();
        $cost = $service->estimateCost($jokeCall);

        $this->assertGreaterThan(0, $cost);
        $this->assertLessThan(1, $cost); // Should be well under $1
    }

    public function test_estimate_cost_includes_deepgram_for_stream(): void
    {
        $jokeCall = JokeCall::create([
            'session_id' => 'test-cost-stream',
            'phone_number' => '+525512345678',
            'joke_category' => 'general',
            'status' => 'completed',
            'joke_text' => str_repeat('a', 200),
            'call_duration_seconds' => 60,
            'stream_sid' => 'MS_test',
            'ip_address' => '127.0.0.1',
        ]);

        $withoutStream = JokeCall::create([
            'session_id' => 'test-cost-nostream',
            'phone_number' => '+525512345679',
            'joke_category' => 'general',
            'status' => 'completed',
            'joke_text' => str_repeat('a', 200),
            'call_duration_seconds' => 60,
            'ip_address' => '127.0.0.1',
        ]);

        $service = new CostTrackingService();
        $costWithStream = $service->estimateCost($jokeCall);
        $costWithoutStream = $service->estimateCost($withoutStream);

        $this->assertGreaterThan($costWithoutStream, $costWithStream);
    }
}
