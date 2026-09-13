<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->string('reference', 40)->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('payment_method', 32)->index();
            $table->string('status', 24)->index();
            $table->unsignedBigInteger('expected_amount_minor');
            $table->unsignedBigInteger('submitted_amount_minor');
            $table->char('currency', 3);
            $table->string('plan_name_snapshot', 100);
            $table->string('plan_interval_snapshot', 16);
            $table->json('plan_snapshot');
            $table->string('payer_name');
            $table->string('payer_bank_name');
            $table->string('transaction_reference', 191);
            $table->dateTime('transferred_at');
            $table->text('notes')->nullable();
            $table->dateTime('submitted_at');
            $table->dateTime('paid_at')->nullable();
            $table->dateTime('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('rejection_reason')->nullable();
            $table->string('provider', 40)->nullable();
            $table->string('provider_payment_id', 191)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'plan_id', 'payment_method', 'status'], 'payments_pending_lookup');
            $table->index(['user_id', 'transaction_reference']);
            $table->unique(['provider', 'provider_payment_id']);
        });

        Schema::create('invoices', function (Blueprint $table): void {
            $table->id();
            $table->string('number', 40)->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();
            $table->string('status', 24)->index();
            $table->unsignedBigInteger('subtotal_minor');
            $table->unsignedBigInteger('total_minor');
            $table->char('currency', 3);
            $table->string('description');
            $table->dateTime('issued_at');
            $table->dateTime('paid_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'issued_at']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('payment_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_id')->constrained()->restrictOnDelete();
            $table->string('type', 32);
            $table->string('disk', 64);
            $table->string('path', 500);
            $table->string('original_name');
            $table->string('mime_type', 150);
            $table->unsignedBigInteger('size');
            $table->timestamps();

            $table->unique(['payment_id', 'type']);
            $table->unique(['disk', 'path']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_attachments');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('payments');
    }
};
