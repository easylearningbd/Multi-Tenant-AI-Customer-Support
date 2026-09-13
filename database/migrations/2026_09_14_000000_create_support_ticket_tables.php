<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_tickets', function (Blueprint $table): void {
            $table->id();
            $table->string('reference', 64)->unique();
            $table->foreignId('requester_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('subject', 200);
            $table->string('priority', 32)->default('medium')->index();
            $table->string('category', 100)->nullable();
            $table->string('status', 32)->default('awaiting_support')->index();
            $table->timestamp('last_activity_at')->index();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['requester_id', 'last_activity_at']);
        });

        Schema::create('support_ticket_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('support_ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sender_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('sender_type', 32);
            $table->text('body');
            $table->timestamps();

            $table->index(['support_ticket_id', 'created_at']);
        });

        Schema::create('support_ticket_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('support_ticket_message_id')->constrained()->cascadeOnDelete();
            $table->string('disk', 64);
            $table->string('path', 500);
            $table->string('original_name');
            $table->string('mime_type', 150);
            $table->unsignedBigInteger('size');
            $table->timestamps();

            $table->index('support_ticket_message_id');
            $table->unique(['disk', 'path']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_ticket_attachments');
        Schema::dropIfExists('support_ticket_messages');
        Schema::dropIfExists('support_tickets');
    }
};
