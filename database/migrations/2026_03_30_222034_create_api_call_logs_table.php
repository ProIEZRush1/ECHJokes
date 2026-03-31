<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('api_call_logs', function (Blueprint $table) {
            $table->id();
            $table->string('service'); // twilio, elevenlabs, deepgram, anthropic
            $table->string('endpoint');
            $table->string('method')->default('POST');
            $table->integer('status_code')->nullable();
            $table->integer('latency_ms')->nullable();
            $table->decimal('cost_estimate', 8, 4)->nullable();
            $table->string('joke_call_id')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_call_logs');
    }
};
