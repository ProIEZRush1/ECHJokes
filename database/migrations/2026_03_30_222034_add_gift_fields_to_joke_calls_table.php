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
            $table->boolean('is_gift')->default(false)->after('delivery_type');
            $table->string('recipient_phone')->nullable()->after('is_gift');
            $table->string('sender_name')->nullable()->after('recipient_phone');
            $table->string('gift_message', 200)->nullable()->after('sender_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('joke_calls', function (Blueprint $table) {
            $table->dropColumn(['is_gift', 'recipient_phone', 'sender_name', 'gift_message']);
        });
    }
};
