<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('jokes_cache', function (Blueprint $table) {
            $table->id();
            $table->text('joke_text');
            $table->string('joke_hash', 64)->unique();
            $table->string('language', 4)->default('en');
            $table->string('source', 32)->default('api-ninjas');
            $table->unsignedInteger('use_count')->default(0);
            $table->timestamp('fetched_at');
            $table->timestamps();
            $table->index('language');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jokes_cache');
    }
};
