<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 页面缓存命中统计（按天聚合）：图表数据来源。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('performance_stats', function (Blueprint $table) {
            $table->date('date')->unique();
            $table->unsignedBigInteger('hits')->default(0);
            $table->unsignedBigInteger('misses')->default(0);
            $table->unsignedBigInteger('total_ms')->default(0)->comment('未命中请求耗时合计（毫秒）');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('performance_stats');
    }
};
