<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table): void {
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('handling_mode', 16)->nullable();
            $table->string('last_message_preview', 500)->nullable();
            $table->string('last_message_sender_type', 24)->nullable();
            $table->unsignedInteger('unread_count')->default(0);
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('archived_at')->nullable();
            $table->softDeletes();

            $table->index(['user_id', 'status', 'last_message_at'], 'conversations_tenant_status_activity_index');
            $table->index(['user_id', 'handling_mode', 'last_message_at'], 'conversations_tenant_mode_activity_index');
            $table->index(['user_id', 'bot_id', 'last_message_at'], 'conversations_tenant_bot_activity_index');
        });

        Schema::table('conversation_messages', function (Blueprint $table): void {
            $table->foreignId('sender_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('message_type', 24)->default('text');
            $table->json('metadata')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();

            $table->index(['conversation_id', 'id'], 'conversation_messages_conversation_id_index');
        });

        Schema::create('conversation_attachments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('bot_id')->constrained()->restrictOnDelete();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('conversation_message_id')->constrained()->cascadeOnDelete();
            $table->string('disk', 50);
            $table->string('path', 500);
            $table->string('original_name', 255);
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('size');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'bot_id', 'conversation_id'], 'conversation_attachments_tenant_bot_conversation_index');
            $table->index(['conversation_message_id', 'created_at'], 'conversation_attachments_message_created_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_attachments');

        Schema::table('conversation_messages', function (Blueprint $table): void {
            $table->dropIndex('conversation_messages_conversation_id_index');
            $table->dropConstrainedForeignId('sender_id');
            $table->dropColumn(['message_type', 'metadata', 'delivered_at', 'read_at']);
        });

        Schema::table('conversations', function (Blueprint $table): void {
            $table->dropIndex('conversations_tenant_status_activity_index');
            $table->dropIndex('conversations_tenant_mode_activity_index');
            $table->dropIndex('conversations_tenant_bot_activity_index');
            $table->dropConstrainedForeignId('assigned_to');
            $table->dropConstrainedForeignId('resolved_by');
            $table->dropColumn([
                'handling_mode', 'last_message_preview', 'last_message_sender_type',
                'unread_count', 'archived_at', 'deleted_at',
            ]);
        });
    }
};
