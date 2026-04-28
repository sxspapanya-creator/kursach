<?php
// database/migrations/2026_04_04_000001_create_all_tables.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Таблица users
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('email', 150)->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password', 100);
            $table->string('google_id', 255)->nullable()->unique();
            $table->timestamps();
        });

        // Таблица categories
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('name', 100);
            $table->enum('type', ['income', 'expense']);
            $table->string('color', 7)->nullable();
            $table->timestamps();
        });

        // Таблица currencies
        Schema::create('currencies', function (Blueprint $table) {
            $table->id();
            $table->string('code', 3)->unique();
            $table->string('name', 50)->nullable();
            $table->string('symbol', 10)->nullable();
            $table->boolean('is_base')->default(false);
            $table->timestamps();
        });

        // Таблица transactions
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->decimal('amount', 10, 2);
            $table->foreignId('currency_id')->constrained();
            $table->enum('type', ['income', 'expense']);
            $table->text('description')->nullable();
            $table->date('date');
            $table->enum('payment_method', ['cash', 'card', 'transfer']);
            $table->timestamps();

            $table->index('date');
        });

        // Таблица plans
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name', 20);
            $table->text('description');
            $table->decimal('price_monthly', 10, 2)->nullable();
            $table->decimal('price_yearly', 10, 2)->nullable();
            $table->foreignId('currency_id')->constrained();
            $table->timestamps();
        });

        // Таблица sessions
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('ip_address', 100)->nullable();
            $table->text('user_agent')->nullable();
            $table->text('payload');
            $table->integer('last_activity')->index();
        });

        // Таблица personal_access_tokens
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('tokenable_type', 100);
            $table->unsignedBigInteger('tokenable_id');
            $table->string('name', 100);
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['tokenable_type', 'tokenable_id']);
        });

        // Таблица category_transaction (промежуточная)
        Schema::create('category_transaction', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->onDelete('cascade');
            $table->foreignId('transaction_id')->constrained()->onDelete('cascade');
        });

        // Таблица currency_rates
        Schema::create('currency_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('from_currency_id')->constrained('currencies')->onDelete('cascade');
            $table->foreignId('to_currency_id')->constrained('currencies')->onDelete('cascade');
            $table->decimal('rate', 10, 6);
            $table->date('date');
            $table->timestamps();

            $table->index('date');
        });

        // Таблица subscriptions
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('plan_id')->constrained();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->timestamp('cancelled_at')->nullable();
            $table->enum('status', ['active', 'expired', 'cancelled']);
            $table->boolean('auto_renew')->default(true);
            $table->timestamps();

            $table->index('status');
        });

        // Таблица subscriptions_payments
        Schema::create('subscriptions_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->onDelete('cascade');
            $table->decimal('amount', 12, 2);
            $table->foreignId('currency_id')->constrained();
            $table->date('payment_date');
            $table->date('period_start');
            $table->date('period_end');
            $table->enum('payment_method', ['card', 'bank_transfer']);
            $table->string('payments_id', 150);
            $table->enum('status', ['pending', 'completed', 'failed', 'refunded']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions_payments');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('currency_rates');
        Schema::dropIfExists('category_transaction');
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('plans');
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('currencies');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('users');
    }
};