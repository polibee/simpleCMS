<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 首页轮播图（后台管理：图片 + 超链接 + 排序/启停）
        Schema::create('cms_banners', function (Blueprint $table) {
            $table->id();
            $table->string('title', 100)->nullable();      // 可选标题（无障碍/悬浮提示）
            $table->string('image_url', 500);              // 图片地址（外链或媒体库 /storage 路径）
            $table->string('link_url', 500)->nullable();   // 点击跳转（可空=纯展示）
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('enabled')->default(true);
            $table->boolean('new_tab')->default(false);
            $table->timestamps();

            $table->index(['enabled', 'sort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_banners');
    }
};
