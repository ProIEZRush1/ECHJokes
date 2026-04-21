<?php

namespace App\Console\Commands;

use App\Enums\JokeCallStatus;
use App\Models\JokeCall;
use App\Models\UserCredit;
use Illuminate\Console\Command;

class CleanupStuckCalls extends Command
{
    protected $signature = 'vacilada:cleanup-stuck-calls {--minutes=10 : Mark as failed if older than N min}';
    protected $description = 'Mark stuck calling/in-progress calls as failed and refund credits';

    public function handle(): int
    {
        $threshold = now()->subMinutes((int) $this->option('minutes'));

        $stuck = JokeCall::whereIn('status', [JokeCallStatus::Calling, JokeCallStatus::InProgress])
            ->where('created_at', '<', $threshold)
            ->get();

        if ($stuck->isEmpty()) {
            $this->info('No stuck calls found.');
            return 0;
        }

        $refunded = 0;
        $this->info("Found {$stuck->count()} stuck calls.");

        foreach ($stuck as $call) {
            $call->update([
                'status' => JokeCallStatus::Failed,
                'failure_reason' => 'Timeout (stuck in ' . $call->status->value . ' for >' . $this->option('minutes') . 'min, likely redeploy or network issue)',
            ]);

            // Refund credit if user_id is present
            if ($call->user_id) {
                $credit = UserCredit::where('user_id', $call->user_id)->first();
                if ($credit) {
                    $credit->increment('credits_remaining', 1);
                    $refunded++;
                }
            }

            $this->line("  Reset call {$call->id} (user={$call->user_id}) → failed, credit refunded");
        }

        $this->info("Cleaned {$stuck->count()} calls, refunded {$refunded} credits.");
        return 0;
    }
}
