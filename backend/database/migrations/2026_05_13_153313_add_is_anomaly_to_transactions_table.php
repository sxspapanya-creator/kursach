<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('transactions', function (Blueprint $table) {
            // Добавляем поле is_anomaly с дефолтным значением false (обычная транзакция)
            $table->boolean('is_anomaly')->default(false)->after('payment_method');

            // Добавляем индекс для быстрой фильтрации
            $table->index('is_anomaly');

            // Составной индекс для частых запросов user_id + is_anomaly
            $table->index(['user_id', 'is_anomaly']);
        });
    }

    public function down()
    {
        Schema::table('transactions', function (Blueprint $table) {
            // Удаляем индексы
            $table->dropIndex(['is_anomaly']);
            $table->dropIndex(['user_id', 'is_anomaly']);

            // Удаляем поле
            $table->dropColumn('is_anomaly');
        });
    }
};