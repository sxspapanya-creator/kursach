<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Бессрочные тарифы: type = NULL.
     */
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->string('type', 25)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->string('type', 25)->nullable(false)->change();
        });
    }
};
