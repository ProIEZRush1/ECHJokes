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
        Schema::create('joke_calls', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('session_id')->unique();
            $table->string('phone_number');
            $table->string('joke_category')->default('general');
            $table->string('status')->default('pending_payment');
            $table->text('joke_text')->nullable();
            $table->string('audio_file_path')->nullable();
            $table->string('stripe_payment_intent_id')->nullable()->unique();
            $table->string('stripe_checkout_session_id')->nullable()->unique();
            $table->string('twilio_call_sid')->nullable()->index();
            $table->integer('call_duration_seconds')->nullable();
            $table->json('ai_transcript')->nullable();
            $table->string('failure_reason')->nullable();
            $table->string('ip_address')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('joke_calls');
    }
};
