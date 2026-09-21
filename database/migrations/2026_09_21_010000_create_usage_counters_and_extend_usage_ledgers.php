<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usage_counters', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('subscription_id')->constrained()->restrictOnDelete();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();
            $table->string('metric', 60);
            $table->dateTime('period_starts_at');
            $table->dateTime('period_ends_at');
            $table->unsignedBigInteger('used')->default(0);
            $table->unsignedBigInteger('reserved')->default(0);
            $table->timestamps();

            $table->unique(
                ['user_id', 'metric', 'period_starts_at', 'period_ends_at'],
                'usage_counters_tenant_metric_period_unique',
            );
            $table->index(['user_id', 'metric', 'period_ends_at'], 'usage_counters_tenant_metric_end_index');
        });

        Schema::table('usage_ledgers', function (Blueprint $table): void {
            $table->foreignId('plan_id')->nullable()->after('subscription_id')->constrained()->nullOnDelete();
            $table->foreignId('knowledge_source_id')->nullable()->after('source_message_id')
                ->constrained('knowledge_sources')->nullOnDelete();
            $table->index(
                ['user_id', 'subscription_id', 'type', 'status', 'period_starts_at'],
                'usage_ledgers_subscription_metric_status_index',
            );
        });
    }

    public function down(): void
    {
        Schema::table('usage_ledgers', function (Blueprint $table): void {
            $table->dropIndex('usage_ledgers_subscription_metric_status_index');
            $table->dropConstrainedForeignId('knowledge_source_id');
            $table->dropConstrainedForeignId('plan_id');
        });

        Schema::dropIfExists('usage_counters');
    }
};
