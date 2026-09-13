<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('trial_claimed_at')->nullable()->after('role')->index();
        });

        Schema::create('subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();
            $table->string('status', 24)->index();
            $table->timestamp('starts_at');
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('current_period_starts_at')->nullable();
            $table->timestamp('current_period_ends_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->string('provider', 40)->nullable();
            $table->string('provider_subscription_id', 191)->nullable();
            $table->string('provider_price_id', 191)->nullable();
            $table->string('trial_claim_key', 191)->nullable()->unique();
            $table->json('plan_snapshot');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'starts_at']);
            $table->index(['user_id', 'status', 'current_period_ends_at']);
            $table->unique(['provider', 'provider_subscription_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['trial_claimed_at']);
            $table->dropColumn('trial_claimed_at');
        });
    }
};
