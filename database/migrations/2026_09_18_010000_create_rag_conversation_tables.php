<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('conversations')) {
            Schema::create('conversations', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('user_id')->constrained()->restrictOnDelete();
                $table->foreignId('bot_id')->constrained()->restrictOnDelete();
                $table->string('status', 32)->index();
                $table->string('channel', 32)->default('subscriber_api');
                $table->string('visitor_identifier', 100)->nullable();
                $table->string('subject', 180)->nullable();
                $table->timestamp('started_at');
                $table->timestamp('last_message_at')->nullable()->index();
                $table->timestamp('handoff_requested_at')->nullable();
                $table->timestamp('ai_resolved_at')->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'bot_id']);
                $table->index(['user_id', 'bot_id', 'status']);
                $table->index(['user_id', 'last_message_at']);
            });
        }

        if (! Schema::hasTable('conversation_messages')) {
            Schema::create('conversation_messages', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('user_id')->constrained()->restrictOnDelete();
                $table->foreignId('bot_id')->constrained()->restrictOnDelete();
                $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
                $table->foreignId('reply_to_message_id')->nullable()->constrained('conversation_messages')->nullOnDelete();
                $table->string('actor_type', 24);
                $table->string('status', 24)->index();
                $table->string('idempotency_key', 64)->nullable();
                $table->longText('body')->nullable();
                $table->string('model', 100)->nullable();
                $table->string('provider_request_id', 150)->nullable();
                $table->unsignedInteger('input_tokens')->nullable();
                $table->unsignedInteger('output_tokens')->nullable();
                $table->unsignedBigInteger('estimated_cost_minor')->nullable();
                $table->char('cost_currency', 3)->nullable();
                $table->unsignedInteger('latency_ms')->nullable();
                $table->string('finish_reason', 60)->nullable();
                $table->decimal('confidence', 8, 7)->nullable();
                $table->string('error_code', 80)->nullable();
                $table->timestamps();

                $table->unique(['user_id', 'bot_id', 'idempotency_key'], 'conversation_messages_tenant_bot_idempotency_unique');
                $table->unique('reply_to_message_id');
                $table->index(['user_id', 'bot_id', 'conversation_id'], 'conversation_messages_tenant_bot_conversation_index');
                $table->index(['conversation_id', 'created_at']);
            });
        }

        if (! Schema::hasTable('message_citations')) {
            Schema::create('message_citations', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained()->restrictOnDelete();
                $table->foreignId('bot_id')->constrained()->restrictOnDelete();
                $table->foreignId('conversation_message_id')->constrained()->cascadeOnDelete();
                $table->foreignId('knowledge_source_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('knowledge_chunk_id')->nullable()->constrained()->nullOnDelete();
                $table->uuid('source_uuid');
                $table->uuid('chunk_uuid');
                $table->string('source_name', 180);
                $table->unsignedSmallInteger('rank');
                $table->decimal('similarity_score', 8, 7);
                $table->timestamps();

                $table->unique(['conversation_message_id', 'rank']);
                $table->index(['user_id', 'bot_id', 'conversation_message_id'], 'message_citations_tenant_bot_message_index');
            });
        }

        if (! Schema::hasTable('retrieval_runs')) {
            Schema::create('retrieval_runs', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('user_id')->constrained()->restrictOnDelete();
                $table->foreignId('bot_id')->constrained()->restrictOnDelete();
                $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
                $table->foreignId('conversation_message_id')->constrained()->cascadeOnDelete();
                $table->char('query_checksum', 64);
                $table->string('embedding_model', 100);
                $table->unsignedInteger('embedding_dimensions');
                $table->unsignedSmallInteger('top_k');
                $table->decimal('minimum_score', 8, 7);
                $table->json('selected_chunk_uuids');
                $table->json('scores');
                $table->unsignedInteger('duration_ms');
                $table->timestamp('created_at');

                $table->index(['user_id', 'bot_id', 'created_at']);
                $table->index(['conversation_id', 'conversation_message_id']);
            });
        }

        if (! Schema::hasTable('usage_ledgers')) {
            Schema::create('usage_ledgers', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('user_id')->constrained()->restrictOnDelete();
                $table->foreignId('bot_id')->constrained()->restrictOnDelete();
                $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('conversation_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('source_message_id')->nullable()->constrained('conversation_messages')->nullOnDelete();
                $table->char('event_key', 64)->unique();
                $table->string('type', 40);
                $table->string('status', 24)->index();
                $table->unsignedInteger('quantity')->default(1);
                $table->string('model', 100)->nullable();
                $table->unsignedInteger('input_tokens')->nullable();
                $table->unsignedInteger('output_tokens')->nullable();
                $table->unsignedBigInteger('estimated_cost_minor')->nullable();
                $table->char('cost_currency', 3)->nullable();
                $table->dateTime('period_starts_at');
                $table->dateTime('period_ends_at');
                $table->timestamp('committed_at')->nullable();
                $table->timestamp('released_at')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'type', 'status', 'period_starts_at'], 'usage_ledgers_tenant_period_status_index');
                $table->index(['user_id', 'bot_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('usage_ledgers');
        Schema::dropIfExists('retrieval_runs');
        Schema::dropIfExists('message_citations');
        Schema::dropIfExists('conversation_messages');
        Schema::dropIfExists('conversations');
    }
};
