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
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone')->nullable()->after('email');
            $table->boolean('is_admin')->default(false)->after('remember_token');
            $table->string('stripe_customer_id')->nullable()->unique()->after('is_admin');
            $table->string('subscription_plan')->nullable()->after('stripe_customer_id');
            $table->timestamp('subscription_ends_at')->nullable()->after('subscription_plan');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone', 'is_admin', 'stripe_customer_id', 'subscription_plan', 'subscription_ends_at']);
        });
    }
};
