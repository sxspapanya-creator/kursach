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
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('price_monthly', 'price_yearly');
            $table->string('code', 10);
            $table->string('type', 25);
            $table->decimal('price')->default(0);
            $table->unique(['code', 'type']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('plan_id')
                ->nullable()
                ->references('id')
                ->on('plans')
                ->onDelete('cascade')
                ->onUpdate('cascade');

            $table->date('plan_expires_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign('users_plan_id_foreign');
            $table->dropColumn('plan_id');
            $table->dropColumn('plan_expires_at');
        });

        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('code');
            $table->dropColumn('type');
            $table->dropColumn('price');
            $table->decimal('price_monthly')->default(0);
            $table->decimal('price_yearly')->default(0);
        });
    }
};