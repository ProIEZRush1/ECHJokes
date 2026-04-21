<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ab_test_events', function (Blueprint $table) {
            $table->id();
            $table->string('test_name', 64)->index();
            $table->string('variant', 32)->index();
            $table->string('event_type', 32);
            $table->uuid('visitor_id')->nullable()->index();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('call_id')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['test_name', 'variant', 'event_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ab_test_events');
    }
};
