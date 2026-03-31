<?php

namespace App\Events;

use App\Models\JokeCall;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class JokeCallStatusUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public JokeCall $jokeCall,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('joke-call.' . $this->jokeCall->session_id),
        ];
    }

    public function broadcastWith(): array
    {
        $data = [
            'id' => $this->jokeCall->id,
            'status' => $this->jokeCall->status->value,
            'status_label' => $this->jokeCall->status->label(),
            'is_terminal' => $this->jokeCall->status->isTerminal(),
        ];

        if ($this->jokeCall->status->isTerminal()) {
            $data['joke_text'] = $this->jokeCall->joke_text;
            $data['call_duration_seconds'] = $this->jokeCall->call_duration_seconds;
            $data['failure_reason'] = $this->jokeCall->failure_reason;
        }

        return $data;
    }
}
