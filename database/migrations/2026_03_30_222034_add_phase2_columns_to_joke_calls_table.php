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
        Schema::table('joke_calls', function (Blueprint $table) {
            $table->string('stream_sid')->nullable()->after('twilio_call_sid');
            $table->string('recording_url')->nullable()->after('ai_transcript');
            $table->string('recording_sid')->nullable()->after('recording_url');
            $table->integer('recording_duration_sec')->nullable()->after('recording_sid');
            $table->string('delivery_type')->default('call')->after('joke_category');
            $table->string('phone_type')->nullable()->after('ip_address');
            $table->string('retry_of')->nullable()->after('failure_reason');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('joke_calls', function (Blueprint $table) {
            $table->dropColumn(['stream_sid', 'recording_url', 'recording_sid', 'recording_duration_sec', 'delivery_type', 'phone_type', 'retry_of']);
        });
    }
};
