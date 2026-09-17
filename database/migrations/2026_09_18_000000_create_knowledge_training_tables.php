<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_sources', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('bot_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('type', 24);
            $table->string('name', 180);
            $table->string('original_filename', 255)->nullable();
            $table->string('file_path', 512)->nullable();
            $table->unsignedBigInteger('file_size_bytes')->default(0);
            $table->text('source_url')->nullable();
            $table->text('sitemap_url')->nullable();
            $table->unsignedSmallInteger('page_limit')->nullable();
            $table->longText('raw_text')->nullable();
            $table->longText('extracted_text')->nullable();
            $table->string('status', 24)->index();
            $table->string('failure_message', 500)->nullable();
            $table->unsignedInteger('chunk_count')->default(0);
            $table->char('content_checksum', 64)->nullable()->index();
            $table->uuid('current_generation_uuid')->nullable();
            $table->uuid('processing_token')->nullable();
            $table->timestamp('last_trained_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'bot_id']);
            $table->index(['user_id', 'bot_id', 'status']);
            $table->index(['bot_id', 'status']);
        });

        Schema::create('knowledge_chunks', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('bot_id')->constrained()->cascadeOnDelete();
            $table->foreignId('knowledge_source_id')->constrained()->cascadeOnDelete();
            $table->uuid('generation_uuid');
            $table->unsignedInteger('chunk_index');
            $table->longText('content');
            $table->unsignedInteger('token_count');
            $table->char('content_checksum', 64)->index();
            $table->json('embedding');
            $table->string('embedding_model', 100);
            $table->unsignedInteger('embedding_dimensions');
            $table->double('embedding_norm');
            $table->boolean('is_active')->default(false);
            $table->timestamp('embedded_at');
            $table->timestamps();

            $table->unique(
                ['knowledge_source_id', 'generation_uuid', 'chunk_index'],
                'knowledge_chunks_source_generation_index_unique',
            );
            $table->index(['user_id', 'bot_id']);
            $table->index(['user_id', 'bot_id', 'knowledge_source_id'], 'knowledge_chunks_tenant_bot_source_index');
            $table->index(['user_id', 'bot_id', 'is_active'], 'knowledge_chunks_tenant_bot_active_index');
            $table->index(['knowledge_source_id', 'chunk_index']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_chunks');
        Schema::dropIfExists('knowledge_sources');
    }
};
