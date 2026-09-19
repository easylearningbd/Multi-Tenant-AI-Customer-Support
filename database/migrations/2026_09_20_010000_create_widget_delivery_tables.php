<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('widget_domains')) {
            Schema::create('widget_domains', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained()->restrictOnDelete();
                $table->foreignId('bot_id')->constrained()->cascadeOnDelete();
                $table->foreignId('widget_id')->constrained()->cascadeOnDelete();
                $table->string('origin', 255);
                $table->timestamps();

                $table->unique(['widget_id', 'origin']);
                $table->index(['user_id', 'bot_id', 'widget_id']);
            });
        }

        if (! Schema::hasTable('visitor_sessions')) {
            Schema::create('visitor_sessions', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('user_id')->constrained()->restrictOnDelete();
                $table->foreignId('bot_id')->constrained()->restrictOnDelete();
                $table->foreignId('widget_id')->constrained()->cascadeOnDelete();
                $table->char('token_hash', 64)->unique();
                $table->string('origin', 255);
                $table->string('channel', 24);
                $table->string('visitor_identifier', 100);
                $table->longText('prechat_data')->nullable();
                $table->dateTime('prechat_completed_at')->nullable();
                $table->dateTime('last_seen_at')->index();
                $table->dateTime('expires_at')->index();
                $table->timestamps();

                $table->index(['user_id', 'bot_id', 'widget_id'], 'visitor_sessions_tenant_bot_widget_index');
                $table->index(['widget_id', 'expires_at']);
            });
        }

        if (! Schema::hasColumn('conversations', 'visitor_session_id')) {
            Schema::table('conversations', function (Blueprint $table): void {
                $table->foreignId('visitor_session_id')
                    ->nullable()
                    ->after('bot_id')
                    ->constrained('visitor_sessions')
                    ->nullOnDelete();
                $table->index(['user_id', 'bot_id', 'visitor_session_id'], 'conversations_tenant_bot_visitor_index');
            });
        }
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table): void {
            $table->dropForeign(['visitor_session_id']);
            $table->dropIndex('conversations_tenant_bot_visitor_index');
            $table->dropColumn('visitor_session_id');
        });

        Schema::dropIfExists('visitor_sessions');
        Schema::dropIfExists('widget_domains');
    }
};
